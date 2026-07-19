<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use App\Models\Enquiry;
use App\Models\Expcate;
use App\Models\Expense;
use App\Models\Expuser;
use App\Models\JobFile;
use App\Models\SalesOrder;
use App\Models\Task;
use App\Models\TaskComment;
use App\Models\TaskCommentFile;
use App\Models\TaskFile;
use App\Services\TaskNotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class TaskController extends Controller
{
    public function index(Request $request)
    {
        $this->authorizeMobileAccess($request);
        $user = $request->user();

        $query = Task::query()
            ->where(function ($query) use ($user) {
                $query->whereHas('assignees', fn ($q) => $q->where('users.id', $user->id))
                    ->orWhereHas('viewers', fn ($q) => $q->where('users.id', $user->id));
            })
            ->with([
                'creator:id,name',
                'job.client',
                'assignees' => fn ($q) => $q->where('users.id', $user->id),
            ])
            ->orderByDesc('created_at');

        if ($request->filled('status') && $request->status !== 'all') {
            if ($request->status === 'overdue' || $request->status === 'over_due') {
                $query->whereHas('assignees', fn ($q) => $q
                    ->where('users.id', $user->id)
                    ->whereNotIn('task_assignees.status', ['completed', 'verified', 'closed']))
                    ->whereNotNull('due_date')
                    ->where('due_date', '<', now()->toDateString());
            } elseif ($request->status === 'viewer') {
                $query->whereHas('viewers', fn ($q) => $q->where('users.id', $user->id))
                    ->whereDoesntHave('assignees', fn ($q) => $q->where('users.id', $user->id));
            } else {
                $query->whereHas('assignees', fn ($q) => $q
                    ->where('users.id', $user->id)
                    ->where('task_assignees.status', $request->status));
            }
        }

        if ($request->filled('search')) {
            $search = $request->string('search')->toString();
            $query->where(fn ($q) => $q
                ->where('title', 'like', "%{$search}%")
                ->orWhere('description', 'like', "%{$search}%"));
        }

        $tasks = $query->paginate((int) $request->query('per_page', 25));

        return response()->json([
            'tasks' => $tasks->through(fn (Task $task) => $this->formatTaskSummary($task, $user)),
        ]);
    }

    public function meta(Request $request)
    {
        $this->authorizeMobileAccess($request);

        return response()->json([
            'enquiry_options' => Enquiry::select('enquiry_no')->orderByDesc('enquiry_no')->take(1000)->pluck('enquiry_no'),
            'sales_order_options' => SalesOrder::select('order_no')->orderByDesc('order_no')->take(1000)->pluck('order_no'),
            'expense_categories' => Expcate::select('name')->orderBy('name')->pluck('name'),
            'expense_done_by' => Expuser::getAllOption(),
        ]);
    }

    public function show(Request $request, Task $task)
    {
        $this->authorizeTaskAccess($request, $task);

        $task->load([
            'creator:id,name',
            'assignees:id,name,email,phone',
            'viewers:id,name,email',
            'files',
            'comments.user',
            'comments.files',
            'job.files',
            'expenses',
        ]);

        $user = $request->user();
        $isAssignee = $task->assignees->contains($user->id);
        $pivot = $isAssignee ? $task->assignees->firstWhere('id', $user->id)->pivot : null;
        $myStatus = $pivot ? $pivot->status : 'viewer';

        return response()->json([
            'task' => $this->formatTaskDetail($task),
            'my_status' => $myStatus,
            'assignees' => $task->assignees->map(fn ($u) => [
                'id' => $u->id,
                'name' => $u->name,
                'phone' => $u->phone,
                'status' => $u->pivot->status,
                'feedback' => $u->pivot->feedback,
                'completed_at' => $u->pivot->completed_at ? \Illuminate\Support\Carbon::parse($u->pivot->completed_at)->format('d-m-Y H:i') : null,
            ]),
            'viewers' => $task->viewers->pluck('name')->all(),
            'files' => $task->files->map(fn ($file) => $this->formatTaskFile($file)),
            'comments' => $task->comments->map(fn ($comment) => $this->formatComment($comment)),
            'job_files' => $task->job?->files->map(fn ($file) => [
                'id' => $file->id,
                'file_name' => $file->file_name,
                'file_type' => $file->file_type,
                'file_size' => $file->file_size,
                'download_url' => route('mobile.tasks.job-file.download', $file->id),
            ])->values() ?? [],
            'expenses' => $task->expenses->map(fn ($expense) => $this->formatExpense($expense)),
        ]);
    }

    public function updateStatus(Request $request, Task $task)
    {
        $this->authorizeTaskAccess($request, $task);
        $user = $request->user();

        $request->validate([
            'status' => 'required|in:accepted,in_progress,completed,verified,closed',
            'comment' => 'nullable|string',
            'enquiry_no' => 'nullable|string|exists:enquiries,enquiry_no',
            'sales_order_no' => 'nullable|string|exists:sales_orders,order_no',
            'files' => 'nullable|array',
            'files.*' => 'nullable|file',
        ]);

        $assignee = $task->assignees()->where('users.id', $user->id)->first();
        if (! $assignee) {
            abort(403, 'Only assignees can update task status.');
        }

        if ($request->status === 'completed') {
            if ($task->need_enquiry_number && (! $task->enquiry_no || $request->has('enquiry_no')) && ! $request->filled('enquiry_no')) {
                throw ValidationException::withMessages(['enquiry_no' => 'Enquiry Number is required for this stage.']);
            }
            if ($task->need_sales_order_number && (! $task->sales_order_no || $request->has('sales_order_no')) && ! $request->filled('sales_order_no')) {
                throw ValidationException::withMessages(['sales_order_no' => 'Sales Order Number is required for this stage.']);
            }
        }

        if (in_array($request->status, ['accepted', 'in_progress']) && $task->start_date && now()->lt($task->start_date)) {
            throw ValidationException::withMessages([
                'status' => 'You cannot accept or start this task before '.$task->start_date->format('d-m-Y H:i').'.',
            ]);
        }

        DB::beginTransaction();
        try {
            if ($request->filled('enquiry_no')) {
                $task->update(['enquiry_no' => $request->enquiry_no]);
            }
            if ($request->filled('sales_order_no')) {
                $task->update(['sales_order_no' => $request->sales_order_no]);
            }

            $prevStatus = $assignee->pivot->status;
            $newStatus = $request->status;
            $updateData = ['status' => $newStatus];
            if ($newStatus === 'completed') {
                $updateData['completed_at'] = now();
                $updateData['feedback'] = $request->comment;
            }
            $task->assignees()->updateExistingPivot($user->id, $updateData);

            $commentText = "Changed status from '".ucfirst($prevStatus)."' to '".ucfirst($newStatus)."'.";
            if ($request->filled('comment')) {
                $commentText .= "\nFeedback: ".$request->comment;
            }

            $comment = TaskComment::create([
                'task_id' => $task->id,
                'user_id' => $user->id,
                'comment' => $commentText,
                'status_update' => $newStatus,
            ]);
            $this->storeCommentFiles($request, $comment);

            $task->load('assignees');
            $allStatuses = $task->assignees->pluck('pivot.status')->all();
            $taskNewStatus = $task->status;
            if (count(array_unique($allStatuses)) === 1 && $allStatuses[0] === 'completed') {
                $taskNewStatus = 'completed';
            } elseif (in_array('in_progress', $allStatuses)) {
                $taskNewStatus = 'in_progress';
            } elseif (in_array('accepted', $allStatuses)) {
                $taskNewStatus = 'accepted';
            }
            if ($taskNewStatus !== $task->status) {
                $task->update(['status' => $taskNewStatus]);
            }

            $nextTaskToStart = null;
            $jobToNotify = null;
            if ($task->job_id) {
                $task->job->recalculateStatus();
                if ($taskNewStatus === 'completed') {
                    $nextTask = Task::where('job_id', $task->job_id)
                        ->where('job_stage_sort_order', $task->job_stage_sort_order + 1)
                        ->first();
                    if ($nextTask && $nextTask->start_on_previous_complete && is_null($nextTask->start_date)) {
                        $nextTask->update(['start_date' => now(), 'start_notified' => true]);
                        $nextTaskToStart = $nextTask;
                    }
                }

                $job = $task->job->fresh();
                if (in_array($job->status, ['completed', 'closed']) && ! $job->job_completed_notified) {
                    $job->update(['job_completed_notified' => true]);
                    $jobToNotify = $job;
                }
            }

            DB::commit();

            if ($nextTaskToStart) {
                TaskNotificationService::notifyTaskStart($nextTaskToStart);
            }
            if ($jobToNotify) {
                TaskNotificationService::notifyJobCompleted($jobToNotify);
            }
            TaskNotificationService::notifyStatusUpdate($task, $user, $newStatus, $request->comment);

            \ActivityLog::add([
                'action' => 'updated',
                'module' => 'taskStatus',
                'data_key' => $task->title." (Mobile user: {$user->name} -> {$newStatus})",
            ]);

            return response()->json(['message' => 'Status updated successfully.']);
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }
    }

    public function addComment(Request $request, Task $task)
    {
        $this->authorizeTaskAccess($request, $task);

        $request->validate([
            'comment' => 'nullable|string',
            'files' => 'nullable|array',
            'files.*' => 'nullable|file',
        ]);

        DB::beginTransaction();
        try {
            $comment = TaskComment::create([
                'task_id' => $task->id,
                'user_id' => $request->user()->id,
                'comment' => $request->comment ?? '',
            ]);
            $storedFiles = $this->storeCommentFiles($request, $comment);
            DB::commit();

            $activityType = $this->detectActivityType($storedFiles);
            TaskNotificationService::notifyActivityUpdate($task, $request->user(), $activityType, $request->comment);

            return response()->json([
                'message' => 'Comment added successfully.',
                'comment' => $this->formatComment($comment->fresh(['user', 'files'])),
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }
    }

    public function storeExpense(Request $request, Task $task)
    {
        $this->authorizeTaskAccess($request, $task);

        if (! $task->assignees()->where('users.id', $request->user()->id)->exists()) {
            abort(403, 'Only assignees can add task expenses.');
        }
        if (! $task->need_expense) {
            abort(422, 'This task does not require expense entry.');
        }

        $request->validate([
            'exp_date' => 'required|date',
            'amount' => 'required|numeric|min:0',
            'amt_type' => 'required|in:Expense,Deposit',
            'exp_cate' => 'required|string',
            'details' => 'nullable|string',
            'job_details' => 'required|string',
            'doneby' => 'nullable|array',
            'job_no' => 'nullable|string',
        ]);

        if (strtotime($request->exp_date) < strtotime('-2 days') && ! $request->user()->can('expense_back_date_entry')) {
            throw ValidationException::withMessages(['exp_date' => 'The Expense Date cannot be older than 2 days.']);
        }

        $doneBy = collect($request->input('doneby', []))->map(function ($item) {
            return is_array($item) ? ($item['id'] ?? null) : $item;
        })->filter()->implode(',');

        $expense = Expense::create([
            'exp_date' => date('Y-m-d', strtotime($request->exp_date)),
            'amount' => $request->amount,
            'amt_type' => $request->amt_type,
            'exp_cate' => $request->exp_cate,
            'details' => $request->details,
            'job_details' => $request->job_details,
            'incharge' => $request->user()->name,
            'doneby' => $doneBy,
            'job_no' => $request->job_no,
            'job_id' => $task->job_id,
            'task_id' => $task->id,
        ]);

        \ActivityLog::add([
            'action' => 'added',
            'module' => 'expense',
            'data_key' => $expense->exp_cate.' (Mobile task: '.$task->title.')',
        ]);

        TaskNotificationService::notifyActivityUpdate(
            $task,
            $request->user(),
            'expense',
            "Category: {$request->exp_cate}, Amount: ".number_format((float) $request->amount, 2)
        );

        return response()->json([
            'message' => 'Expense created and linked successfully.',
            'expense' => $this->formatExpense($expense),
        ], 201);
    }

    public function downloadTaskFile(Request $request, TaskFile $taskFile)
    {
        $this->authorizeTaskAccess($request, $taskFile->task);

        return $this->download($taskFile->file_path, $taskFile->file_name);
    }

    public function downloadCommentFile(Request $request, TaskCommentFile $commentFile)
    {
        $this->authorizeTaskAccess($request, $commentFile->comment->task);

        return $this->download($commentFile->file_path, $commentFile->file_name);
    }

    public function downloadJobFile(Request $request, JobFile $jobFile)
    {
        $task = Task::where('job_id', $jobFile->job_id)
            ->where(function ($query) use ($request) {
                $query->whereHas('assignees', fn ($q) => $q->where('users.id', $request->user()->id))
                    ->orWhereHas('viewers', fn ($q) => $q->where('users.id', $request->user()->id));
            })
            ->firstOrFail();

        $this->authorizeTaskAccess($request, $task);

        return $this->download($jobFile->file_path, $jobFile->file_name);
    }

    private function authorizeMobileAccess(Request $request): void
    {
        if (! $request->user()->tokenCan('mobile:tasks') || ! $request->user()->can('task_MyTasksList')) {
            abort(403, 'Unauthorized mobile task access.');
        }
    }

    private function authorizeTaskAccess(Request $request, Task $task): void
    {
        $this->authorizeMobileAccess($request);
        $userId = $request->user()->id;

        $hasAccess = $task->assignees()->where('users.id', $userId)->exists()
            || $task->viewers()->where('users.id', $userId)->exists();

        if (! $hasAccess) {
            abort(403, 'You do not have access to this task.');
        }
    }

    private function formatTaskSummary(Task $task, $user): array
    {
        $isAssignee = $task->assignees->contains($user->id);
        $pivot = $isAssignee ? $task->assignees->firstWhere('id', $user->id)->pivot : null;

        return [
            'id' => $task->id,
            'title' => $task->title,
            'description' => $task->description,
            'priority' => $task->priority,
            'due_date' => $task->due_date?->format('d-m-Y H:i'),
            'creator_name' => $task->creator?->name,
            'status' => $task->status,
            'is_assignee' => $isAssignee,
            'my_status' => $pivot ? $pivot->status : 'viewer',
            'my_feedback' => $pivot ? $pivot->feedback : null,
            'completed_at' => $pivot && $pivot->completed_at ? \Illuminate\Support\Carbon::parse($pivot->completed_at)->format('d-m-Y H:i') : null,
            'job_name' => $task->job?->title,
            'job_id' => $task->job_id,
            'client_name' => $task->job?->client?->cl_name,
            'start_date' => $task->start_date?->format('d-m-Y H:i'),
            'start_date_raw' => $task->start_date?->toIso8601String(),
            'estimated_hours' => $task->estimated_hours,
            'job_stage_sort_order' => $task->job_stage_sort_order,
            'enquiry_no' => $task->enquiry_no,
            'sales_order_no' => $task->sales_order_no,
            'need_enquiry_number' => (bool) $task->need_enquiry_number,
            'need_sales_order_number' => (bool) $task->need_sales_order_number,
            'need_expense' => (bool) $task->need_expense,
        ];
    }

    private function formatTaskDetail(Task $task): array
    {
        return [
            'id' => $task->id,
            'title' => $task->title,
            'description' => $task->description,
            'status' => $task->status,
            'priority' => $task->priority,
            'due_date' => $task->due_date?->format('d-m-Y H:i'),
            'created_at' => $task->created_at?->format('d-m-Y H:i'),
            'creator' => $task->creator?->name,
            'start_date' => $task->start_date?->format('d-m-Y H:i'),
            'start_date_raw' => $task->start_date?->toIso8601String(),
            'estimated_hours' => $task->estimated_hours,
            'job_stage_sort_order' => $task->job_stage_sort_order,
            'enquiry_no' => $task->enquiry_no,
            'sales_order_no' => $task->sales_order_no,
            'need_enquiry_number' => (bool) $task->need_enquiry_number,
            'need_sales_order_number' => (bool) $task->need_sales_order_number,
            'need_expense' => (bool) $task->need_expense,
        ];
    }

    private function formatTaskFile(TaskFile $file): array
    {
        return [
            'id' => $file->id,
            'file_name' => $file->file_name,
            'file_type' => $file->file_type,
            'file_size' => $file->file_size,
            'download_url' => route('mobile.tasks.file.download', $file->id),
        ];
    }

    private function formatComment(TaskComment $comment): array
    {
        return [
            'id' => $comment->id,
            'comment' => $comment->comment,
            'status_update' => $comment->status_update,
            'created_at' => $comment->created_at?->format('d-m-Y H:i'),
            'user' => [
                'name' => $comment->user?->name,
                'role' => $comment->user?->role_name,
            ],
            'files' => $comment->files->map(fn ($file) => [
                'id' => $file->id,
                'file_name' => $file->file_name,
                'file_type' => $file->file_type,
                'file_size' => $file->file_size,
                'download_url' => route('mobile.tasks.comment-file.download', $file->id),
            ]),
        ];
    }

    private function formatExpense(Expense $expense): array
    {
        return [
            'id' => $expense->id,
            'exp_date' => date('d-m-Y', strtotime($expense->exp_date)),
            'amount' => number_format((float) $expense->amount, 2),
            'amt_type' => $expense->amt_type,
            'exp_cate' => $expense->exp_cate,
            'doneby' => $expense->doneby,
            'details' => $expense->details,
            'job_details' => $expense->job_details,
            'job_no' => $expense->job_no,
        ];
    }

    private function storeCommentFiles(Request $request, TaskComment $comment): array
    {
        $stored = [];
        if (! $request->hasFile('files')) {
            return $stored;
        }

        foreach ($request->file('files') as $file) {
            $originalName = $file->getClientOriginalName();
            $storedName = uniqid('comm_', true).'_'.time().'.'.$file->getClientOriginalExtension();
            $path = 'task_comment_files/'.$comment->id;
            $file->storeAs($path, $storedName, 'local');

            $stored[] = $comment->files()->create([
                'file_path' => $path.'/'.$storedName,
                'file_name' => $originalName,
                'file_type' => $file->getMimeType(),
                'file_size' => $file->getSize(),
            ]);
        }

        return $stored;
    }

    private function detectActivityType(array $files): string
    {
        $hasVoice = false;
        $hasFiles = false;

        foreach ($files as $file) {
            $name = strtolower($file->file_name);
            if (str_contains($name, 'voice_note') || str_ends_with($name, '.webm') || str_ends_with($name, '.ogg') || str_ends_with($name, '.wav') || str_ends_with($name, '.mp3') || str_contains($file->file_type, 'audio')) {
                $hasVoice = true;
            } else {
                $hasFiles = true;
            }
        }

        if ($hasVoice && ! $hasFiles) {
            return 'voice_note';
        }
        if ($hasFiles && ! $hasVoice) {
            return 'file_upload';
        }

        return 'comment';
    }

    private function download(string $path, string $fileName)
    {
        if (! Storage::disk('local')->exists($path)) {
            abort(404, 'File not found.');
        }

        return response()->download(storage_path('app/'.$path), $fileName);
    }
}
