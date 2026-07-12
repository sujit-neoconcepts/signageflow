<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Workflow;
use App\Models\WorkflowStage;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use ProtoneMedia\LaravelQueryBuilderInertiaJs\InertiaTable;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class WorkflowController extends Controller
{
    protected $resourceNeo = [
        'resourceName' => 'workflow',
        'resourceTitle' => 'Workflows',
        'iconPath' => 'M12,6V9L16,5L12,1V4A8,8 0 0,0 4,12C4,13.57 4.46,15.03 5.24,16.26L6.7,14.8C6.25,13.97 6,13 6,12A6,6 0 0,1 12,6M18.76,7.74L17.3,9.2C17.74,10.04 18,11 18,12A6,6 0 0,1 12,18V15L8,19L12,23V20A8,8 0 0,0 20,12C20,10.43 19.54,8.97 18.76,7.74Z',
    ];

    public function __construct()
    {
        $this->middleware('can:workflow_list', ['only' => ['index']]);
        $this->middleware('can:workflow_create', ['only' => ['create', 'store', 'clone']]);
        $this->middleware('can:workflow_edit', ['only' => ['edit', 'update']]);
        $this->middleware('can:workflow_delete', ['only' => ['destroy', 'bulkDestroy']]);
        $this->middleware('can:workflow_view', ['only' => ['show', 'getStagesJson']]);
    }

    public function index()
    {
        $query = Workflow::query()->with(['creator', 'stages']);

        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                Collection::wrap($value)->each(function ($value) use ($query) {
                    $query->orWhere('name', 'LIKE', "%{$value}%")
                        ->orWhere('description', 'LIKE', "%{$value}%");
                });
            });
        });

        $perPage = request()->query('perPage') ?? 10;
        $workflows = QueryBuilder::for($query)
            ->defaultSort('-created_at')
            ->allowedSorts(['name', 'is_active', 'created_at'])
            ->allowedFilters([
                'is_active',
                $globalSearch,
            ])
            ->paginate($perPage)
            ->withQueryString();

        if (Auth::user()->can('workflow_delete')) {
            $this->resourceNeo['bulkActions']['bulkDelete'] = [];
        }

        return Inertia::render('Admin/WorkflowIndexView', [
            'workflows' => $workflows,
            'resourceNeo' => $this->resourceNeo,
        ])->table(function (InertiaTable $table) {
            $table->withGlobalSearch()
                ->column('name', 'Name', searchable: true, sortable: true)
                ->column('stages_count', 'Stages')
                ->column('is_active', 'Status', sortable: true)
                ->column('creator.name', 'Created By')
                ->column('actions', 'Actions')
                ->perPageOptions([10, 15, 30, 50]);
        });
    }

    public function create()
    {
        $executives = User::role('executive')->select('id', 'name as label', 'email')->orderBy('name')->get();

        return Inertia::render('Admin/WorkflowAddEditView', [
            'executives' => $executives,
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'is_active' => 'required|boolean',
            'stages' => 'required|array|min:1',
            'stages.*.name' => 'required|string|max:255',
            'stages.*.description' => 'nullable|string',
            'stages.*.default_estimated_hours' => 'nullable|numeric|min:0',
            'stages.*.need_enquiry_number' => 'nullable|boolean',
            'stages.*.need_sales_order_number' => 'nullable|boolean',
            'stages.*.executives' => 'nullable|array',
            'stages.*.executives.*.id' => 'required|exists:users,id',
        ]);

        DB::beginTransaction();
        try {
            $workflow = Workflow::create([
                'name' => $request->name,
                'description' => $request->description,
                'is_active' => $request->is_active,
                'created_by' => Auth::id(),
            ]);

            foreach ($request->stages as $index => $stageData) {
                $stage = $workflow->stages()->create([
                    'name' => $stageData['name'],
                    'description' => $stageData['description'] ?? null,
                    'sort_order' => $index,
                    'default_estimated_hours' => $stageData['default_estimated_hours'] ?? null,
                    'need_enquiry_number' => $stageData['need_enquiry_number'] ?? false,
                    'need_sales_order_number' => $stageData['need_sales_order_number'] ?? false,
                ]);

                if (! empty($stageData['executives'])) {
                    $executiveIds = collect($stageData['executives'])->pluck('id')->all();
                    $stage->defaultExecutives()->attach($executiveIds);
                }
            }

            DB::commit();

            \ActivityLog::add([
                'action' => 'created',
                'module' => 'workflow',
                'data_key' => $workflow->name,
            ]);

            return redirect()->route('workflow.index')->with(['message' => 'Workflow Created Successfully', 'msg_type' => 'success']);
        } catch (\Exception $e) {
            DB::rollBack();

            return redirect()->back()->withInput()->with(['message' => 'Failed to create workflow: '.$e->getMessage(), 'msg_type' => 'danger']);
        }
    }

    public function edit(Workflow $workflow)
    {
        $workflow->load(['stages.defaultExecutives:id,name as label,email']);

        $executives = User::role('executive')->select('id', 'name as label', 'email')->orderBy('name')->get();

        return Inertia::render('Admin/WorkflowAddEditView', [
            'formdata' => $workflow,
            'executives' => $executives,
        ]);
    }

    public function update(Request $request, Workflow $workflow)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'is_active' => 'required|boolean',
            'stages' => 'required|array|min:1',
            'stages.*.id' => 'nullable|integer',
            'stages.*.name' => 'required|string|max:255',
            'stages.*.description' => 'nullable|string',
            'stages.*.default_estimated_hours' => 'nullable|numeric|min:0',
            'stages.*.need_enquiry_number' => 'nullable|boolean',
            'stages.*.need_sales_order_number' => 'nullable|boolean',
            'stages.*.executives' => 'nullable|array',
            'stages.*.executives.*.id' => 'required|exists:users,id',
        ]);

        DB::beginTransaction();
        try {
            $workflow->update([
                'name' => $request->name,
                'description' => $request->description,
                'is_active' => $request->is_active,
            ]);

            // Get existing stage IDs
            $existingStageIds = $workflow->stages()->pluck('id')->all();
            $submittedStageIds = collect($request->stages)->pluck('id')->filter()->all();

            // Delete removed stages
            $toDelete = array_diff($existingStageIds, $submittedStageIds);
            if (! empty($toDelete)) {
                WorkflowStage::whereIn('id', $toDelete)->delete();
            }

            // Update or create stages
            foreach ($request->stages as $index => $stageData) {
                if (! empty($stageData['id']) && in_array($stageData['id'], $existingStageIds)) {
                    // Update existing stage
                    $stage = WorkflowStage::find($stageData['id']);
                    $stage->update([
                        'name' => $stageData['name'],
                        'description' => $stageData['description'] ?? null,
                        'sort_order' => $index,
                        'default_estimated_hours' => $stageData['default_estimated_hours'] ?? null,
                        'need_enquiry_number' => $stageData['need_enquiry_number'] ?? false,
                        'need_sales_order_number' => $stageData['need_sales_order_number'] ?? false,
                    ]);
                } else {
                    // Create new stage
                    $stage = $workflow->stages()->create([
                        'name' => $stageData['name'],
                        'description' => $stageData['description'] ?? null,
                        'sort_order' => $index,
                        'default_estimated_hours' => $stageData['default_estimated_hours'] ?? null,
                        'need_enquiry_number' => $stageData['need_enquiry_number'] ?? false,
                        'need_sales_order_number' => $stageData['need_sales_order_number'] ?? false,
                    ]);
                }

                // Sync executives
                $executiveIds = ! empty($stageData['executives'])
                    ? collect($stageData['executives'])->pluck('id')->all()
                    : [];
                $stage->defaultExecutives()->sync($executiveIds);
            }

            DB::commit();

            \ActivityLog::add([
                'action' => 'updated',
                'module' => 'workflow',
                'data_key' => $workflow->name,
            ]);

            return redirect()->route('workflow.index')->with(['message' => 'Workflow Updated Successfully', 'msg_type' => 'success']);
        } catch (\Exception $e) {
            DB::rollBack();

            return redirect()->back()->with(['message' => 'Failed to update workflow: '.$e->getMessage(), 'msg_type' => 'danger']);
        }
    }

    public function destroy(Workflow $workflow)
    {
        $name = $workflow->name;
        $workflow->delete();

        \ActivityLog::add([
            'action' => 'deleted',
            'module' => 'workflow',
            'data_key' => $name,
        ]);

        return redirect()->route('workflow.index')->with(['message' => 'Workflow Deleted Successfully', 'msg_type' => 'success']);
    }

    public function bulkDestroy(Request $request)
    {
        $ids = $request->ids;
        if (empty($ids)) {
            return redirect()->back()->with(['message' => 'No Workflows selected', 'msg_type' => 'danger']);
        }

        Workflow::whereIn('id', $ids)->delete();

        \ActivityLog::add([
            'action' => 'deleted',
            'module' => 'workflow',
            'data_key' => count($ids).' Workflows (Bulk)',
        ]);

        return redirect()->route('workflow.index')->with(['message' => 'Selected Workflows Deleted Successfully', 'msg_type' => 'success']);
    }

    public function clone(Workflow $workflow)
    {
        DB::beginTransaction();
        try {
            // Find a unique name
            $baseName = $workflow->name.' (Copy)';
            $name = $baseName;
            $counter = 1;
            while (Workflow::where('name', $name)->exists()) {
                $name = $baseName.' '.$counter;
                $counter++;
            }

            $newWorkflow = Workflow::create([
                'name' => $name,
                'description' => $workflow->description,
                'is_active' => $workflow->is_active,
                'created_by' => Auth::id(),
            ]);

            // Clone stages
            $workflow->load('stages.defaultExecutives');
            foreach ($workflow->stages as $stage) {
                $newStage = $newWorkflow->stages()->create([
                    'name' => $stage->name,
                    'description' => $stage->description,
                    'sort_order' => $stage->sort_order,
                    'default_estimated_hours' => $stage->default_estimated_hours,
                    'need_enquiry_number' => $stage->need_enquiry_number,
                    'need_sales_order_number' => $stage->need_sales_order_number,
                ]);

                if ($stage->defaultExecutives->isNotEmpty()) {
                    $newStage->defaultExecutives()->attach($stage->defaultExecutives->pluck('id')->all());
                }
            }

            DB::commit();

            \ActivityLog::add([
                'action' => 'cloned',
                'module' => 'workflow',
                'data_key' => $newWorkflow->name,
            ]);

            return redirect()->route('workflow.index')->with(['message' => 'Workflow Cloned Successfully', 'msg_type' => 'success']);
        } catch (\Exception $e) {
            DB::rollBack();

            return redirect()->back()->with(['message' => 'Failed to clone workflow: '.$e->getMessage(), 'msg_type' => 'danger']);
        }
    }

    /**
     * Return stages with default executives for a workflow (JSON API).
     * Used when selecting a workflow during job creation.
     */
    public function getStagesJson(Workflow $workflow)
    {
        $workflow->load(['stages.defaultExecutives:id,name as label,email']);

        return response()->json([
            'stages' => $workflow->stages->map(function ($stage) {
                return [
                    'id' => $stage->id,
                    'name' => $stage->name,
                    'description' => $stage->description,
                    'sort_order' => $stage->sort_order,
                    'default_estimated_hours' => $stage->default_estimated_hours,
                    'need_enquiry_number' => (bool) $stage->need_enquiry_number,
                    'need_sales_order_number' => (bool) $stage->need_sales_order_number,
                    'executives' => $stage->defaultExecutives->map(function ($exec) {
                        return [
                            'id' => $exec->id,
                            'label' => $exec->label,
                            'email' => $exec->email,
                        ];
                    }),
                ];
            }),
        ]);
    }
}
