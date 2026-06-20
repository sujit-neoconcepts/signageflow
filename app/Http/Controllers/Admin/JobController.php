<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\Job;
use App\Models\JobFile;
use App\Models\Task;
use App\Models\User;
use App\Models\Workflow;
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

class JobController extends Controller
{
    protected $resourceNeo = [
        'resourceName' => 'job',
        'resourceTitle' => 'Jobs',
        'iconPath' => 'M20,6C20.58,6 21.05,6.2 21.42,6.59C21.8,7 22,7.45 22,8V19C22,19.55 21.8,20 21.42,20.41C21.05,20.8 20.58,21 20,21H4C3.42,21 2.95,20.8 2.58,20.41C2.2,20 2,19.55 2,19V8C2,7.45 2.2,7 2.58,6.59C2.95,6.2 3.42,6 4,6H8V4C8,3.42 8.2,2.95 8.58,2.58C8.95,2.2 9.42,2 10,2H14C14.58,2 15.05,2.2 15.42,2.58C15.8,2.95 16,3.42 16,4V6H20M4,8V19H20V8H4M14,6V4H10V6H14Z',
    ];

    public function __construct()
    {
        $this->middleware('can:job_list', ['only' => ['index']]);
        $this->middleware('can:job_create', ['only' => ['create', 'store', 'uploadTempFiles']]);
        $this->middleware('can:job_edit', ['only' => ['edit', 'update']]);
        $this->middleware('can:job_delete', ['only' => ['destroy']]);
        $this->middleware('can:job_view', ['only' => ['show', 'recalculateStatus']]);
    }

    public function index()
    {
        $query = Job::query()->with(['client', 'workflow', 'creator', 'tasks']);

        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                Collection::wrap($value)->each(function ($value) use ($query) {
                    $query->orWhere('title', 'LIKE', "%{$value}%")
                        ->orWhere('description', 'LIKE', "%{$value}%");
                });
            });
        });

        $perPage = request()->query('perPage') ?? 10;
        $jobs = QueryBuilder::for($query)
            ->defaultSort('-created_at')
            ->allowedSorts(['title', 'due_date', 'status', 'created_at'])
            ->allowedFilters([
                'status',
                AllowedFilter::exact('client_id'),
                AllowedFilter::exact('workflow_id'),
                AllowedFilter::scope('due_date_start'),
                AllowedFilter::scope('due_date_end'),
                $globalSearch,
            ])
            ->paginate($perPage)
            ->withQueryString();

        $statusOptions = [
            'not_started' => 'Not Started',
            'in_progress' => 'In Progress',
            'completed' => 'Completed',
            'closed' => 'Closed',
        ];

        $clients = Client::select('id', 'cl_name as name')->orderBy('cl_name')->get();
        $clientOptions = [];
        foreach ($clients as $c) {
            $clientOptions[$c->id] = $c->name;
        }

        $workflows = Workflow::where('is_active', true)->select('id', 'name')->orderBy('name')->get();
        $workflowOptions = [];
        foreach ($workflows as $w) {
            $workflowOptions[$w->id] = $w->name;
        }

        return Inertia::render('Admin/JobIndexView', [
            'jobs' => $jobs,
            'resourceNeo' => $this->resourceNeo,
        ])->table(function (InertiaTable $table) use ($statusOptions, $clientOptions, $workflowOptions) {
            $table->withGlobalSearch()
                ->column('title', 'Title', searchable: true, sortable: true)
                ->column('client.cl_name', 'Client')
                ->column('workflow.name', 'Workflow')
                ->column('due_date', 'Due Date', sortable: true)
                ->column('status', 'Status', sortable: true)
                ->column('progress', 'Progress')
                ->column('actions', 'Actions')
                ->perPageOptions([10, 15, 30, 50])
                ->selectFilter(key: 'status', label: 'Status', options: $statusOptions)
                ->selectFilter(key: 'client_id', label: 'Client', options: $clientOptions)
                ->selectFilter(key: 'workflow_id', label: 'Workflow', options: $workflowOptions);
        });
    }

    public function create()
    {
        $executives = User::role('executive')->select('id', 'name as label', 'email')->orderBy('name')->get();
        $loopUsers = User::select('id', 'name as label', 'email')->orderBy('name')->get();
        $clients = Client::select('id', 'cl_name as label')->orderBy('cl_name')->get();
        $workflows = Workflow::where('is_active', true)->with('stages')->select('id', 'name as label')->orderBy('name')->get();

        return Inertia::render('Admin/JobAddEditView', [
            'executives' => $executives,
            'loopUsers' => $loopUsers,
            'clients' => $clients,
            'workflows' => $workflows,
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'client_id' => 'nullable|exists:clients,id',
            'workflow_id' => 'nullable|exists:workflows,id',
            'due_date' => 'required|date',
            'estimated_hours' => 'nullable|numeric|min:0',
            'stages' => 'nullable|array',
            'stages.*.name' => 'required|string|max:255',
            'stages.*.assignees' => 'required|array|min:1',
            'stages.*.assignees.*.id' => 'required|exists:users,id',
            'stages.*.loop_users' => 'nullable|array',
            'stages.*.loop_users.*.id' => 'required|exists:users,id',
            'stages.*.estimated_hours' => 'nullable|numeric|min:0',
            'stages.*.start_date' => 'nullable|date',
            'stages.*.end_date' => 'nullable|date',
            'stages.*.start_on_previous_complete' => 'nullable|boolean',
        ]);

        DB::beginTransaction();
        try {
            $job = Job::create([
                'title' => $request->title,
                'description' => $request->description,
                'client_id' => $request->client_id,
                'workflow_id' => $request->workflow_id,
                'due_date' => $request->due_date,
                'estimated_hours' => $request->estimated_hours,
                'status' => 'not_started',
                'created_by' => Auth::id(),
            ]);

            // Create tasks for each stage
            if (! empty($request->stages)) {
                foreach ($request->stages as $index => $stageData) {
                    $task = Task::create([
                        'title' => $stageData['name'],
                        'description' => $stageData['description'] ?? null,
                        'creator_id' => Auth::id(),
                        'due_date' => $stageData['end_date'] ?? $request->due_date,
                        'start_date' => $stageData['start_date'] ?? null,
                        'end_date' => $stageData['end_date'] ?? null,
                        'estimated_hours' => $stageData['estimated_hours'] ?? null,
                        'start_on_previous_complete' => $stageData['start_on_previous_complete'] ?? false,
                        'status' => 'pending',
                        'priority' => 'medium',
                        'job_id' => $job->id,
                        'job_stage_sort_order' => $index,
                        'notify_channels' => ['email'],
                        'reminder_before_due' => 60,
                    ]);

                    // Attach assignees
                    $assigneeIds = collect($stageData['assignees'])->pluck('id')->all();
                    $task->assignees()->attach($assigneeIds, ['status' => 'pending']);

                    // Attach loop users
                    if (! empty($stageData['loop_users'])) {
                        $viewerIds = collect($stageData['loop_users'])->pluck('id')->all();
                        $task->viewers()->attach($viewerIds);
                    }

                    // Notify assignees
                    TaskNotificationService::notifyNewTask($task);
                }
            }

            // Move temp files to job
            if ($request->has('temp_files') && is_array($request->temp_files)) {
                foreach ($request->temp_files as $tf) {
                    $oldPath = 'temp_job_files/'.$tf['stored_name'];
                    if (Storage::disk('local')->exists($oldPath)) {
                        $newDir = 'job_files/'.$job->id;
                        $newPath = $newDir.'/'.$tf['stored_name'];
                        Storage::disk('local')->move($oldPath, $newPath);

                        $job->files()->create([
                            'file_path' => $newPath,
                            'file_name' => $tf['original_name'],
                            'file_type' => $tf['mime_type'],
                            'file_size' => $tf['file_size'],
                        ]);
                    }
                }
            }

            DB::commit();

            \ActivityLog::add([
                'action' => 'created',
                'module' => 'job',
                'data_key' => $job->title,
            ]);

            return redirect()->route('job.index')->with(['message' => 'Job Created Successfully', 'msg_type' => 'success']);
        } catch (\Exception $e) {
            DB::rollBack();

            return redirect()->back()->withInput()->with(['message' => 'Failed to create job: '.$e->getMessage(), 'msg_type' => 'danger']);
        }
    }

    public function show(Job $job)
    {
        $job->load([
            'client',
            'workflow',
            'creator',
            'files',
            'tasks.assignees',
            'tasks.viewers',
            'tasks.comments.user',
        ]);

        $tasks = $job->tasks->map(function ($task) {
            return [
                'id' => $task->id,
                'title' => $task->title,
                'description' => $task->description,
                'status' => $task->status,
                'priority' => $task->priority,
                'due_date' => $task->due_date ? $task->due_date->format('d-m-Y H:i') : null,
                'start_date' => $task->start_date ? $task->start_date->format('d-m-Y H:i') : null,
                'end_date' => $task->end_date ? $task->end_date->format('d-m-Y H:i') : null,
                'estimated_hours' => $task->estimated_hours,
                'start_on_previous_complete' => $task->start_on_previous_complete,
                'job_stage_sort_order' => $task->job_stage_sort_order,
                'assignees' => $task->assignees->map(fn ($a) => [
                    'id' => $a->id,
                    'name' => $a->name,
                    'status' => $a->pivot->status,
                ]),
                'viewers' => $task->viewers->pluck('name'),
                'comments_count' => $task->comments->count(),
            ];
        });

        $files = $job->files->map(function ($f) {
            return [
                'id' => $f->id,
                'file_name' => $f->file_name,
                'file_type' => $f->file_type,
                'file_size' => $f->file_size,
                'download_url' => route('job.file.download', $f->id),
            ];
        });

        // Calculate progress
        $totalTasks = $job->tasks->count();
        $completedTasks = $job->tasks->whereIn('status', ['completed', 'verified', 'closed'])->count();
        $progress = $totalTasks > 0 ? round(($completedTasks / $totalTasks) * 100) : 0;

        return Inertia::render('Admin/JobShowView', [
            'job' => [
                'id' => $job->id,
                'title' => $job->title,
                'description' => $job->description,
                'status' => $job->status,
                'due_date' => $job->due_date->format('d-m-Y H:i'),
                'estimated_hours' => $job->estimated_hours,
                'created_at' => $job->created_at->format('d-m-Y H:i'),
                'client' => $job->client ? $job->client->cl_name : null,
                'workflow' => $job->workflow ? $job->workflow->name : null,
                'creator' => $job->creator->name,
            ],
            'tasks' => $tasks,
            'files' => $files,
            'progress' => $progress,
            'totalTasks' => $totalTasks,
            'completedTasks' => $completedTasks,
        ]);
    }

    public function edit(Job $job)
    {
        $job->load(['tasks.assignees:id,name as label,email', 'tasks.viewers:id,name as label,email', 'files']);

        $executives = User::role('executive')->select('id', 'name as label', 'email')->orderBy('name')->get();
        $loopUsers = User::select('id', 'name as label', 'email')->orderBy('name')->get();
        $clients = Client::select('id', 'cl_name as label')->orderBy('cl_name')->get();
        $workflows = Workflow::where('is_active', true)->with('stages')->select('id', 'name as label')->orderBy('name')->get();

        $formdata = [
            'id' => $job->id,
            'title' => $job->title,
            'description' => $job->description,
            'client_id' => $job->client_id,
            'workflow_id' => $job->workflow_id,
            'due_date' => $job->due_date ? $job->due_date->format('Y-m-d H:i:s') : null,
            'estimated_hours' => $job->estimated_hours,
            'files' => $job->files,
        ];

        return Inertia::render('Admin/JobAddEditView', [
            'formdata' => $formdata,
            'executives' => $executives,
            'loopUsers' => $loopUsers,
            'clients' => $clients,
            'workflows' => $workflows,
        ]);
    }

    public function update(Request $request, Job $job)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'client_id' => 'nullable|exists:clients,id',
            'due_date' => 'required|date',
            'estimated_hours' => 'nullable|numeric|min:0',
        ]);

        DB::beginTransaction();
        try {
            $job->update([
                'title' => $request->title,
                'description' => $request->description,
                'client_id' => $request->client_id,
                'due_date' => $request->due_date,
                'estimated_hours' => $request->estimated_hours,
            ]);

            // Move temp files
            if ($request->has('temp_files') && is_array($request->temp_files)) {
                foreach ($request->temp_files as $tf) {
                    $oldPath = 'temp_job_files/'.$tf['stored_name'];
                    if (Storage::disk('local')->exists($oldPath)) {
                        $newDir = 'job_files/'.$job->id;
                        $newPath = $newDir.'/'.$tf['stored_name'];
                        Storage::disk('local')->move($oldPath, $newPath);

                        $job->files()->create([
                            'file_path' => $newPath,
                            'file_name' => $tf['original_name'],
                            'file_type' => $tf['mime_type'],
                            'file_size' => $tf['file_size'],
                        ]);
                    }
                }
            }

            DB::commit();

            \ActivityLog::add([
                'action' => 'updated',
                'module' => 'job',
                'data_key' => $job->title,
            ]);

            return redirect()->route('job.index')->with(['message' => 'Job Updated Successfully', 'msg_type' => 'success']);
        } catch (\Exception $e) {
            DB::rollBack();

            return redirect()->back()->with(['message' => 'Failed to update job: '.$e->getMessage(), 'msg_type' => 'danger']);
        }
    }

    public function destroy(Job $job)
    {
        // Unlink tasks but don't delete them
        Task::where('job_id', $job->id)->update(['job_id' => null, 'job_stage_sort_order' => null]);

        // Delete job files from storage
        foreach ($job->files as $file) {
            Storage::disk('local')->delete($file->file_path);
        }

        $title = $job->title;
        $job->delete();

        \ActivityLog::add([
            'action' => 'deleted',
            'module' => 'job',
            'data_key' => $title,
        ]);

        return redirect()->route('job.index')->with(['message' => 'Job Deleted Successfully', 'msg_type' => 'success']);
    }

    public function recalculateStatus(Job $job)
    {
        $job->recalculateStatus();

        return response()->json(['status' => $job->fresh()->status]);
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
            $file->storeAs('temp_job_files', $storedName, 'local');

            $uploaded[] = [
                'original_name' => $originalName,
                'stored_name' => $storedName,
                'mime_type' => $file->getMimeType(),
                'file_size' => $file->getSize(),
            ];
        }

        return response()->json(['files' => $uploaded]);
    }

    public function deleteFile(JobFile $jobFile)
    {
        Storage::disk('local')->delete($jobFile->file_path);
        $jobFile->delete();

        return response()->json(['success' => true]);
    }

    public function downloadFile(JobFile $jobFile)
    {
        $path = storage_path('app/'.$jobFile->file_path);

        if (! file_exists($path)) {
            abort(404, 'File not found.');
        }

        return response()->download($path, $jobFile->file_name);
    }
}
