<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Job;
use App\Models\Task;
use App\Models\TaskComment;
use App\Models\TaskCommentFile;
use App\Models\TaskFile;
use App\Models\User;
use App\Services\TaskNotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use ProtoneMedia\LaravelQueryBuilderInertiaJs\InertiaTable;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class TaskController extends Controller
{
    protected $resourceNeo = [
        'resourceName' => 'task',
        'resourceTitle' => 'Tasks',
        'iconPath' => 'M19,3H14.82C14.4,1.84 13.3,1 12,1C10.7,1 9.6,1.84 9.18,3H5A2,2 0 0,0 3,5V19A2,2 0 0,0 5,21H19A2,2 0 0,0 21,19V5A2,2 0 0,0 19,3M12,3A1,1 0 0,1 13,4A1,1 0 0,1 12,5A1,1 0 0,1 11,4A1,1 0 0,1 12,3M14,17H7V15H14V17M17,13H7V11H17V13M17,9H7V7H17V9Z',
    ];

    public function __construct()
    {
        $this->middleware('can:task_list', ['only' => ['index']]);
        $this->middleware('can:task_create', ['only' => ['create', 'store', 'uploadTempFiles']]);
        $this->middleware('can:task_edit', ['only' => ['edit', 'update']]);
        $this->middleware('can:task_delete', ['only' => ['destroy', 'bulkDestroy']]);
        $this->middleware('can:task_view', ['only' => ['show']]);
        $this->middleware('can:task_MyTasksList', ['only' => ['myTasks', 'updateAssigneeStatus', 'addComment']]);
    }

    public function index()
    {
        $query = Task::query()->with(['creator', 'assignees', 'job']);

        // Global search callback
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                Collection::wrap($value)->each(function ($value) use ($query) {
                    $query->orWhere('title', 'LIKE', "%{$value}%")
                        ->orWhere('description', 'LIKE', "%{$value}%");
                });
            });
        });

        $perPage = request()->query('perPage') ?? 10;
        $tasks = QueryBuilder::for($query)
            ->defaultSort('-created_at')
            ->allowedSorts(['title', 'due_date', 'status', 'priority', 'created_at'])
            ->allowedFilters([
                'status',
                'priority',
                AllowedFilter::exact('creator_id'),
                AllowedFilter::scope('due_date_start'),
                AllowedFilter::scope('due_date_end'),
                AllowedFilter::callback('assignee_id', function ($query, $value) {
                    $query->whereHas('assignees', function ($q) use ($value) {
                        $q->where('users.id', $value);
                    });
                }),
                AllowedFilter::exact('job_id'),
                $globalSearch,
            ])
            ->paginate($perPage)
            ->withQueryString();

        if (Auth::user()->can('task_delete')) {
            $this->resourceNeo['bulkActions']['bulkDelete'] = [];
        }

        // Fetch managers and executives for filters
        $executives = User::role('executive')->select('id', 'name as label')->orderBy('name')->get();
        $creators = User::whereHas('tasksCreated')->select('id', 'name as label')->orderBy('name')->get();

        $statusOptions = [
            'pending' => 'Pending',
            'accepted' => 'Accepted',
            'in_progress' => 'In Progress',
            'completed' => 'Completed',
            'verified' => 'Verified',
            'closed' => 'Closed',
        ];

        $allUsers = User::select('id', 'name')->orderBy('name')->get();
        $assigneeOptions = [];
        foreach ($allUsers as $usr) {
            $assigneeOptions[$usr->id] = $usr->name;
        }

        $allJobs = Job::select('id', 'title')->orderBy('title')->get();
        $jobOptions = [];
        foreach ($allJobs as $jb) {
            $jobOptions[$jb->id] = $jb->title;
        }

        return Inertia::render('Admin/TaskIndexView', [
            'tasks' => $tasks,
            'resourceNeo' => $this->resourceNeo,
            'executives' => $executives,
            'creators' => $creators,
        ])->table(function (InertiaTable $table) use ($statusOptions, $assigneeOptions, $jobOptions) {
            $table->withGlobalSearch()
                ->column('title', 'Title', searchable: true, sortable: true)
                ->column('job.title', 'Job')
                ->column('due_date', 'Due Date', sortable: true)
                ->column('status', 'Status', sortable: true)
                ->column('priority', 'Priority', sortable: true)
                ->column('creator.name', 'Created By')
                ->column('assignees', 'Assignees')
                ->column('actions', 'Actions')
                ->perPageOptions([10, 15, 30, 50, 100])
                ->selectFilter(key: 'status', label: 'Status', options: $statusOptions)
                ->selectFilter(key: 'assignee_id', label: 'Assignee', options: $assigneeOptions)
                ->selectFilter(key: 'job_id', label: 'Job', options: $jobOptions)
                ->dateFilter(key: 'due_date_start', label: 'Due Date From')
                ->dateFilter(key: 'due_date_end', label: 'Due Date To');
        });
    }

    public function create()
    {
        $executives = User::role('executive')->select('id', 'name as label', 'email')->orderBy('name')->get();
        $loopUsers = User::select('id', 'name as label', 'email')->orderBy('name')->get();
        $openJobs = Job::whereIn('status', ['not_started', 'in_progress'])->select('id', 'title as label')->orderBy('title')->get();

        return Inertia::render('Admin/TaskAddEditView', [
            'executives' => $executives,
            'loopUsers' => $loopUsers,
            'openJobs' => $openJobs,
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'due_date' => 'required|date',
            'priority' => 'required|in:low,medium,high,urgent',
            'assignees' => 'required|array|min:1',
            'assignees.*.id' => 'required|exists:users,id',
            'loop_users' => 'nullable|array',
            'loop_users.*.id' => 'required|exists:users,id',
            'notify_channels' => 'required|array|min:1',
            'reminder_before_due' => 'required|integer|min:0',
            'is_recurring' => 'required|boolean',
            'recurrence_type' => 'required_if:is_recurring,true|nullable|in:daily,weekly,monthly,yearly',
            'recurrence_config' => 'nullable|array',
            'recurrence_end_date' => 'required_if:is_recurring,true|nullable|date|after_or_equal:due_date',
            'job_id' => 'nullable|exists:jobs_manager,id',
        ]);

        DB::beginTransaction();
        try {
            $task = Task::create([
                'title' => $request->title,
                'description' => $request->description,
                'creator_id' => Auth::id(),
                'due_date' => $request->due_date,
                'status' => 'pending',
                'priority' => $request->priority,
                'is_recurring' => $request->is_recurring,
                'recurrence_type' => $request->recurrence_type,
                'recurrence_config' => $request->recurrence_config,
                'recurrence_end_date' => $request->recurrence_end_date,
                'notify_channels' => $request->notify_channels,
                'reminder_before_due' => $request->reminder_before_due,
                'job_id' => $request->job_id,
            ]);

            // Sync assignees
            $assigneeIds = collect($request->assignees)->pluck('id')->all();
            $task->assignees()->attach($assigneeIds, ['status' => 'pending']);

            // Sync loop users
            if (! empty($request->loop_users)) {
                $viewerIds = collect($request->loop_users)->pluck('id')->all();
                $task->viewers()->attach($viewerIds);
            }

            // Move temp files
            if ($request->has('temp_files') && is_array($request->temp_files)) {
                foreach ($request->temp_files as $tf) {
                    $oldPath = 'temp_task_files/'.$tf['stored_name'];
                    if (Storage::disk('local')->exists($oldPath)) {
                        $newDir = 'task_files/'.$task->id;
                        $newPath = $newDir.'/'.$tf['stored_name'];
                        Storage::disk('local')->move($oldPath, $newPath);

                        $task->files()->create([
                            'file_path' => $newPath,
                            'file_name' => $tf['original_name'],
                            'file_type' => $tf['mime_type'],
                            'file_size' => $tf['file_size'],
                        ]);
                    }
                }
            }

            if ($task->job_id) {
                $task->job->recalculateStatus();
            }

            DB::commit();

            // Notify
            TaskNotificationService::notifyNewTask($task);

            \ActivityLog::add([
                'action' => 'created',
                'module' => 'task',
                'data_key' => $task->title,
            ]);

            return redirect()->route('task.index')->with(['message' => 'Task Created Successfully', 'msg_type' => 'success']);
        } catch (\Exception $e) {
            DB::rollBack();

            return redirect()->back()->withInput()->with(['message' => 'Failed to create task: '.$e->getMessage(), 'msg_type' => 'danger']);
        }
    }

    public function edit(Task $task)
    {
        $task->load(['assignees:id,name as label,email', 'viewers:id,name as label,email', 'files', 'job:id,title']);

        $task->due_date_formatted = $task->due_date ? $task->due_date->format('Y-m-d H:i:s') : null;
        $task->recurrence_end_date_formatted = $task->recurrence_end_date ? $task->recurrence_end_date->format('Y-m-d') : null;

        $executives = User::role('executive')->select('id', 'name as label', 'email')->orderBy('name')->get();
        $loopUsers = User::select('id', 'name as label', 'email')->orderBy('name')->get();
        $openJobs = Job::whereIn('status', ['not_started', 'in_progress'])->select('id', 'title as label')->orderBy('title')->get();

        return Inertia::render('Admin/TaskAddEditView', [
            'formdata' => $task,
            'executives' => $executives,
            'loopUsers' => $loopUsers,
            'openJobs' => $openJobs,
        ]);
    }

    public function update(Request $request, Task $task)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'due_date' => 'required|date',
            'priority' => 'required|in:low,medium,high,urgent',
            'assignees' => 'required|array|min:1',
            'assignees.*.id' => 'required|exists:users,id',
            'loop_users' => 'nullable|array',
            'loop_users.*.id' => 'required|exists:users,id',
            'notify_channels' => 'required|array|min:1',
            'reminder_before_due' => 'required|integer|min:0',
            'is_recurring' => 'required|boolean',
            'recurrence_type' => 'required_if:is_recurring,true|nullable|in:daily,weekly,monthly,yearly',
            'recurrence_config' => 'nullable|array',
            'recurrence_end_date' => 'required_if:is_recurring,true|nullable|date|after_or_equal:due_date',
            'job_id' => 'nullable|exists:jobs_manager,id',
        ]);

        DB::beginTransaction();
        try {
            $task->update([
                'title' => $request->title,
                'description' => $request->description,
                'due_date' => $request->due_date,
                'priority' => $request->priority,
                'is_recurring' => $request->is_recurring,
                'recurrence_type' => $request->recurrence_type,
                'recurrence_config' => $request->recurrence_config,
                'recurrence_end_date' => $request->recurrence_end_date,
                'notify_channels' => $request->notify_channels,
                'reminder_before_due' => $request->reminder_before_due,
                'job_id' => $request->job_id,
            ]);

            // Sync assignees
            $newAssigneeIds = collect($request->assignees)->pluck('id')->all();
            $currentAssignees = $task->assignees()->pluck('user_id')->all();

            $toDetach = array_diff($currentAssignees, $newAssigneeIds);
            $task->assignees()->detach($toDetach);

            $toAttach = array_diff($newAssigneeIds, $currentAssignees);
            $task->assignees()->attach($toAttach, ['status' => 'pending']);

            // Sync loop users
            $newViewerIds = collect($request->loop_users ?? [])->pluck('id')->all();
            $task->viewers()->sync($newViewerIds);

            // Move temp files
            if ($request->has('temp_files') && is_array($request->temp_files)) {
                foreach ($request->temp_files as $tf) {
                    $oldPath = 'temp_task_files/'.$tf['stored_name'];
                    if (Storage::disk('local')->exists($oldPath)) {
                        $newDir = 'task_files/'.$task->id;
                        $newPath = $newDir.'/'.$tf['stored_name'];
                        Storage::disk('local')->move($oldPath, $newPath);

                        $task->files()->create([
                            'file_path' => $newPath,
                            'file_name' => $tf['original_name'],
                            'file_type' => $tf['mime_type'],
                            'file_size' => $tf['file_size'],
                        ]);
                    }
                }
            }

            if ($task->job_id) {
                $task->job->recalculateStatus();
            }

            DB::commit();

            \ActivityLog::add([
                'action' => 'updated',
                'module' => 'task',
                'data_key' => $task->title,
            ]);

            return redirect()->route('task.index')->with(['message' => 'Task Updated Successfully', 'msg_type' => 'success']);
        } catch (\Exception $e) {
            DB::rollBack();

            return redirect()->back()->with(['message' => 'Failed to update task: '.$e->getMessage(), 'msg_type' => 'danger']);
        }
    }

    public function show(Task $task)
    {
        $task->load([
            'creator',
            'assignees:id,name,email,phone',
            'viewers:id,name,email',
            'files',
            'comments.user',
            'comments.files',
            'job.files',
        ]);

        $comments = $task->comments->map(function ($c) {
            return [
                'id' => $c->id,
                'comment' => $c->comment,
                'status_update' => $c->status_update,
                'created_at' => $c->created_at->format('d-m-Y H:i'),
                'user' => [
                    'name' => $c->user->name,
                    'role' => $c->user->role_name,
                ],
                'files' => $c->files->map(function ($f) {
                    return [
                        'id' => $f->id,
                        'file_name' => $f->file_name,
                        'file_type' => $f->file_type,
                        'file_size' => $f->file_size,
                        'download_url' => route('task.commentFile.download', $f->id),
                    ];
                }),
            ];
        });

        $files = $task->files->map(function ($f) {
            return [
                'id' => $f->id,
                'file_name' => $f->file_name,
                'file_type' => $f->file_type,
                'file_size' => $f->file_size,
                'download_url' => route('task.file.download', $f->id),
                'delete_url' => route('task.file.destroy', $f->id),
            ];
        });

        $assigneeStatuses = $task->assignees->map(function ($u) {
            return [
                'id' => $u->id,
                'name' => $u->name,
                'phone' => $u->phone,
                'status' => $u->pivot->status,
                'feedback' => $u->pivot->feedback,
                'completed_at' => $u->pivot->completed_at ? \Illuminate\Support\Carbon::parse($u->pivot->completed_at)->format('d-m-Y H:i') : null,
            ];
        });

        // Job context data
        $jobContext = null;
        $jobFiles = [];
        if ($task->job) {
            $jobContext = [
                'id' => $task->job->id,
                'title' => $task->job->title,
                'status' => $task->job->status,
                'due_date' => $task->job->due_date ? $task->job->due_date->format('d-m-Y H:i') : null,
            ];
            $jobFiles = $task->job->files->map(function ($f) {
                return [
                    'id' => $f->id,
                    'file_name' => $f->file_name,
                    'file_type' => $f->file_type,
                    'file_size' => $f->file_size,
                    'download_url' => route('job.file.download', $f->id),
                ];
            });
        }

        return Inertia::render('Admin/TaskShowView', [
            'task' => [
                'id' => $task->id,
                'title' => $task->title,
                'description' => $task->description,
                'status' => $task->status,
                'priority' => $task->priority,
                'due_date' => $task->due_date->format('d-m-Y H:i'),
                'is_recurring' => $task->is_recurring,
                'recurrence_type' => $task->recurrence_type,
                'recurrence_config' => $task->recurrence_config,
                'recurrence_end_date' => $task->recurrence_end_date ? $task->recurrence_end_date->format('d-m-Y') : null,
                'created_at' => $task->created_at->format('d-m-Y H:i'),
                'creator' => $task->creator->name,
            ],
            'assignees' => $assigneeStatuses,
            'viewers' => $task->viewers->pluck('name')->all(),
            'files' => $files,
            'comments' => $comments,
            'jobContext' => $jobContext,
            'jobFiles' => $jobFiles,
        ]);
    }

    public function destroy(Task $task)
    {
        $task->load('files');
        foreach ($task->files as $file) {
            Storage::disk('local')->delete($file->file_path);
        }

        $task->load('comments.files');
        foreach ($task->comments as $comment) {
            foreach ($comment->files as $cf) {
                Storage::disk('local')->delete($cf->file_path);
            }
        }

        $title = $task->title;
        $task->delete();

        \ActivityLog::add([
            'action' => 'deleted',
            'module' => 'task',
            'data_key' => $title,
        ]);

        return redirect()->route('task.index')->with(['message' => 'Task Deleted Successfully', 'msg_type' => 'success']);
    }

    public function bulkDestroy(Request $request)
    {
        $ids = $request->ids;
        if (empty($ids)) {
            return redirect()->back()->with(['message' => 'No Tasks selected', 'msg_type' => 'danger']);
        }

        $tasks = Task::with(['files', 'comments.files'])->whereIn('id', $ids)->get();
        foreach ($tasks as $task) {
            foreach ($task->files as $file) {
                Storage::disk('local')->delete($file->file_path);
            }
            foreach ($task->comments as $comment) {
                foreach ($comment->files as $cf) {
                    Storage::disk('local')->delete($cf->file_path);
                }
            }
            $task->delete();
        }

        \ActivityLog::add([
            'action' => 'deleted',
            'module' => 'task',
            'data_key' => count($ids).' Tasks (Bulk)',
        ]);

        return redirect()->route('task.index')->with(['message' => 'Selected Tasks Deleted Successfully', 'msg_type' => 'success']);
    }

    public function myTasks()
    {
        $user = Auth::user();

        $tasks = Task::whereHas('assignees', function ($q) use ($user) {
            $q->where('users.id', $user->id);
        })
            ->orWhereHas('viewers', function ($q) use ($user) {
                $q->where('users.id', $user->id);
            })
            ->with(['creator', 'job', 'assignees' => function ($q) use ($user) {
                $q->where('users.id', $user->id);
            }])
            ->orderBy('created_at', 'desc')
            ->get();

        $formattedTasks = $tasks->map(function ($task) use ($user) {
            $isAssignee = $task->assignees->contains($user->id);
            $pivot = $isAssignee ? $task->assignees->firstWhere('id', $user->id)->pivot : null;

            return [
                'id' => $task->id,
                'title' => $task->title,
                'description' => $task->description,
                'priority' => $task->priority,
                'due_date' => $task->due_date->format('d-m-Y H:i'),
                'creator_name' => $task->creator->name,
                'status' => $task->status,
                'is_assignee' => $isAssignee,
                'my_status' => $pivot ? $pivot->status : 'viewer',
                'my_feedback' => $pivot ? $pivot->feedback : null,
                'completed_at' => $pivot && $pivot->completed_at ? \Illuminate\Support\Carbon::parse($pivot->completed_at)->format('d-m-Y H:i') : null,
                'job_name' => $task->job ? $task->job->title : null,
                'start_date' => $task->start_date ? $task->start_date->format('d-m-Y H:i') : null,
                'start_date_raw' => $task->start_date ? $task->start_date->toIso8601String() : null,
                'estimated_hours' => $task->estimated_hours,
                'job_stage_sort_order' => $task->job_stage_sort_order,
            ];
        });

        return Inertia::render('Admin/MyTasksView', [
            'tasks' => $formattedTasks,
        ]);
    }

    public function updateAssigneeStatus(Request $request, Task $task)
    {
        $user = Auth::user();

        $request->validate([
            'status' => 'required|in:accepted,in_progress,completed,verified,closed',
            'comment' => 'nullable|string',
            'files' => 'nullable|array',
            'files.*' => 'nullable|file',
        ]);

        $assignee = $task->assignees()->where('users.id', $user->id)->first();
        if (! $assignee) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        if (in_array($request->status, ['accepted', 'in_progress']) && $task->start_date && now()->lt($task->start_date)) {
            return redirect()->back()->with([
                'message' => 'You cannot accept or start this task before its start date/time: ' . $task->start_date->format('d-m-Y H:i'),
                'msg_type' => 'danger'
            ]);
        }

        DB::beginTransaction();
        try {
            $prevStatus = $assignee->pivot->status;
            $newStatus = $request->status;

            $updateData = ['status' => $newStatus];
            if ($newStatus === 'completed') {
                $updateData['completed_at'] = now();
                $updateData['feedback'] = $request->comment;
            }
            $task->assignees()->updateExistingPivot($user->id, $updateData);

            $commentText = "Changed status from '".ucfirst($prevStatus)."' to '".ucfirst($newStatus)."'.";
            if (! empty($request->comment)) {
                $commentText .= "\nFeedback: ".$request->comment;
            }

            $commentRecord = TaskComment::create([
                'task_id' => $task->id,
                'user_id' => $user->id,
                'comment' => $commentText,
                'status_update' => $newStatus,
            ]);

            if ($request->hasFile('files')) {
                foreach ($request->file('files') as $file) {
                    $originalName = $file->getClientOriginalName();
                    $storedName = uniqid('comm_', true).'_'.time().'.'.$file->getClientOriginalExtension();
                    $path = 'task_comment_files/'.$commentRecord->id;
                    $file->storeAs($path, $storedName, 'local');

                    $commentRecord->files()->create([
                        'file_path' => $path.'/'.$storedName,
                        'file_name' => $originalName,
                        'file_type' => $file->getMimeType(),
                        'file_size' => $file->getSize(),
                    ]);
                }
            }

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

            // Recalculate job status if task belongs to a job
            if ($task->job_id) {
                $task->job->recalculateStatus();
            }

            DB::commit();

            // Notify Task Creator
            TaskNotificationService::notifyStatusUpdate($task, $user, $newStatus, $request->comment);

            \ActivityLog::add([
                'action' => 'updated',
                'module' => 'taskStatus',
                'data_key' => $task->title." (User: {$user->name} -> {$newStatus})",
            ]);

            return redirect()->back()->with(['message' => 'Status Updated Successfully', 'msg_type' => 'success']);
        } catch (\Exception $e) {
            DB::rollBack();

            return redirect()->back()->with(['message' => 'Failed to update status: '.$e->getMessage(), 'msg_type' => 'danger']);
        }
    }

    public function addComment(Request $request, Task $task)
    {
        $request->validate([
            'comment' => 'required|string',
            'files' => 'nullable|array',
            'files.*' => 'nullable|file',
        ]);

        DB::beginTransaction();
        try {
            $comment = TaskComment::create([
                'task_id' => $task->id,
                'user_id' => Auth::id(),
                'comment' => $request->comment,
            ]);

            if ($request->hasFile('files')) {
                foreach ($request->file('files') as $file) {
                    $originalName = $file->getClientOriginalName();
                    $storedName = uniqid('comm_', true).'_'.time().'.'.$file->getClientOriginalExtension();
                    $path = 'task_comment_files/'.$comment->id;
                    $file->storeAs($path, $storedName, 'local');

                    $comment->files()->create([
                        'file_path' => $path.'/'.$storedName,
                        'file_name' => $originalName,
                        'file_type' => $file->getMimeType(),
                        'file_size' => $file->getSize(),
                    ]);
                }
            }

            DB::commit();

            return redirect()->back()->with(['message' => 'Comment Added Successfully', 'msg_type' => 'success']);
        } catch (\Exception $e) {
            DB::rollBack();

            return redirect()->back()->with(['message' => 'Failed to add comment: '.$e->getMessage(), 'msg_type' => 'danger']);
        }
    }

    public function updateTaskStatus(Request $request, Task $task)
    {
        $request->validate([
            'status' => 'required|in:pending,accepted,in_progress,completed,verified,closed',
            'comment' => 'nullable|string',
        ]);

        DB::beginTransaction();
        try {
            $prevStatus = $task->status;
            $newStatus = $request->status;

            $task->update(['status' => $newStatus]);

            if (in_array($newStatus, ['verified', 'closed'])) {
                $task->assignees()->updateExistingPivot($task->assignees->pluck('id'), [
                    'status' => $newStatus,
                ]);
            }

            if ($newStatus === 'in_progress' || $newStatus === 'pending') {
                $task->assignees()->updateExistingPivot($task->assignees->pluck('id'), [
                    'status' => $newStatus,
                    'completed_at' => null,
                ]);
            }

            $commentText = "Manager updated overall task status from '".ucfirst($prevStatus)."' to '".ucfirst($newStatus)."'.";
            if (! empty($request->comment)) {
                $commentText .= "\nReason: ".$request->comment;
            }

            TaskComment::create([
                'task_id' => $task->id,
                'user_id' => Auth::id(),
                'comment' => $commentText,
                'status_update' => $newStatus,
            ]);

            if ($task->job_id) {
                $task->job->recalculateStatus();
            }

            DB::commit();

            \ActivityLog::add([
                'action' => 'updated',
                'module' => 'taskStatus',
                'data_key' => $task->title." (Overall status: {$newStatus})",
            ]);

            return redirect()->back()->with(['message' => 'Task Status Updated', 'msg_type' => 'success']);
        } catch (\Exception $e) {
            DB::rollBack();

            return redirect()->back()->with(['message' => 'Failed to update task status: '.$e->getMessage(), 'msg_type' => 'danger']);
        }
    }

    public function uploadTempFiles(Request $request)
    {
        $request->validate([
            'files' => 'required|array|min:1',
            'files.*' => 'required|file',
        ]);

        $uploaded = [];
        foreach ($request->file('files') as $file) {
            $originalName = $file->getClientOriginalName();
            $storedName = uniqid('temp_', true).'_'.time().'.'.$file->getClientOriginalExtension();
            $file->storeAs('temp_task_files', $storedName, 'local');

            $uploaded[] = [
                'original_name' => $originalName,
                'stored_name' => $storedName,
                'mime_type' => $file->getMimeType(),
                'file_size' => $file->getSize(),
            ];
        }

        return response()->json(['files' => $uploaded]);
    }

    public function deleteFile(TaskFile $taskFile)
    {
        Storage::disk('local')->delete($taskFile->file_path);
        $taskFile->delete();

        return response()->json(['success' => true]);
    }

    public function downloadFile(TaskFile $taskFile)
    {
        $path = storage_path('app/'.$taskFile->file_path);

        if (! file_exists($path)) {
            abort(404, 'File not found.');
        }

        return response()->download($path, $taskFile->file_name);
    }

    public function downloadCommentFile(TaskCommentFile $commentFile)
    {
        $path = storage_path('app/'.$commentFile->file_path);

        if (! file_exists($path)) {
            abort(404, 'File not found.');
        }

        return response()->download($path, $commentFile->file_name);
    }

    public function jsonDetails(Task $task)
    {
        $task->load([
            'creator',
            'assignees:id,name,email,phone',
            'viewers:id,name,email',
            'files',
            'comments.user',
            'comments.files',
            'job.files',
        ]);

        $comments = $task->comments->map(function ($c) {
            return [
                'id' => $c->id,
                'comment' => $c->comment,
                'status_update' => $c->status_update,
                'created_at' => $c->created_at->format('d-m-Y H:i'),
                'user' => [
                    'name' => $c->user->name,
                    'role' => $c->user->role_name,
                ],
                'files' => $c->files->map(function ($f) {
                    return [
                        'id' => $f->id,
                        'file_name' => $f->file_name,
                        'file_type' => $f->file_type,
                        'file_size' => $f->file_size,
                        'download_url' => route('task.commentFile.download', $f->id),
                    ];
                }),
            ];
        });

        $files = $task->files->map(function ($f) {
            return [
                'id' => $f->id,
                'file_name' => $f->file_name,
                'file_type' => $f->file_type,
                'file_size' => $f->file_size,
                'download_url' => route('task.file.download', $f->id),
                'delete_url' => route('task.file.destroy', $f->id),
            ];
        });

        $jobFiles = [];
        if ($task->job) {
            $jobFiles = $task->job->files->map(function ($f) {
                return [
                    'id' => $f->id,
                    'file_name' => $f->file_name,
                    'file_type' => $f->file_type,
                    'file_size' => $f->file_size,
                    'download_url' => route('job.file.download', $f->id),
                ];
            });
        }

        $assigneeStatuses = $task->assignees->map(function ($u) {
            return [
                'id' => $u->id,
                'name' => $u->name,
                'phone' => $u->phone,
                'status' => $u->pivot->status,
                'feedback' => $u->pivot->feedback,
                'completed_at' => $u->pivot->completed_at ? \Illuminate\Support\Carbon::parse($u->pivot->completed_at)->format('d-m-Y H:i') : null,
            ];
        });

        return response()->json([
            'task' => [
                'id' => $task->id,
                'title' => $task->title,
                'description' => $task->description,
                'status' => $task->status,
                'priority' => $task->priority,
                'due_date' => $task->due_date->format('d-m-Y H:i'),
                'is_recurring' => $task->is_recurring,
                'recurrence_type' => $task->recurrence_type,
                'recurrence_config' => $task->recurrence_config,
                'recurrence_end_date' => $task->recurrence_end_date ? $task->recurrence_end_date->format('d-m-Y') : null,
                'created_at' => $task->created_at->format('d-m-Y H:i'),
                'creator' => $task->creator->name,
                'start_date' => $task->start_date ? $task->start_date->format('d-m-Y H:i') : null,
                'start_date_raw' => $task->start_date ? $task->start_date->toIso8601String() : null,
                'estimated_hours' => $task->estimated_hours,
                'job_stage_sort_order' => $task->job_stage_sort_order,
            ],
            'assignees' => $assigneeStatuses,
            'viewers' => $task->viewers->pluck('name')->all(),
            'files' => $files,
            'comments' => $comments,
            'job_files' => $jobFiles,
        ]);
    }
}
