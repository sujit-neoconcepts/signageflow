<?php

namespace App\Http\Controllers\Admin;

use Inertia\Inertia;
use App\Models\ConsumableInternalNameGroup;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use App\Http\Controllers\Controller;
use Spatie\QueryBuilder\QueryBuilder;
use Spatie\QueryBuilder\AllowedFilter;
use ProtoneMedia\LaravelQueryBuilderInertiaJs\InertiaTable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use App\Helpers\ActivityLog;

class ConsumableInternalNameGroupController extends Controller
{
    protected $resourceNeo = [
        'resourceName' => 'consumableInternalNameGroup',
        'resourceTitle' => 'Internal name Group',
        'iconPath' => 'M1,1V5H2V19H1V23H5V22H19V23H23V19H22V5H23V1H19V2H5V1M5,4H19V5H20V19H19V20H5V19H4V5H5M6,6V14H9V18H18V9H14V6M8,8H12V12H8M14,11H16V16H11V14H14',
        'actions' => ['c', 'r', 'u', 'd']
    ];

    public function __construct()
    {
        $this->middleware('can:consumableInternalNameGroup_list', ['only' => ['index', 'show']]);
        $this->middleware('can:consumableInternalNameGroup_create', ['only' => ['create', 'store', 'importView', 'import']]);
        $this->middleware('can:consumableInternalNameGroup_delete', ['only' => ['destroy', 'bulkDestroy']]);
        $this->middleware('can:consumableInternalNameGroup_edit', ['only' => ['edit', 'update']]);
    }

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $formInfo = ConsumableInternalNameGroup::formInfo();
        $formInfoMulti = [];
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) use ($formInfo, $formInfoMulti) {
            $query->where(function ($query) use ($value, $formInfo, $formInfoMulti) {
                Collection::wrap($value)->each(function ($value) use ($query, $formInfo, $formInfoMulti) {
                    foreach (array_keys($formInfo) as $key) {
                        $query->orWhere($key, 'LIKE', "%{$value}%");
                    }
                });
            });
        });
        $perPage = request()->query('perPage') ?? 10;
        $resourceData = QueryBuilder::for(ConsumableInternalNameGroup::class)
            ->defaultSort('name')
            ->allowedSorts(array_keys($formInfo))
            ->allowedFilters(array_merge(array_keys($formInfo), [$globalSearch]))
            ->paginate($perPage)
            ->withQueryString();

        if (Auth::user()->can('consumableInternalNameGroup_delete')) {
            $this->resourceNeo['bulkActions'] = ['bulk_delete' => []];
        }

        if (Auth::user()->can('consumableInternalNameGroup_export')) {
            $this->resourceNeo['bulkActions']['csvExport'] = [];
        }

        // Add import link to extraMainLinks
        $this->resourceNeo['extraMainLinks'] = [
            [
                'label' => 'Import',
                'link' => 'consumableInternalNameGroup.import',
                'icon' => 'M14,12L10,8V11H2V13H10V16M20,18V6C20,4.89 19.1,4 18,4H6A2,2 0 0,0 4,6V9H6V6H18V18H6V15H4V18A2,2 0 0,0 6,20H18A2,2 0 0,0 20,18Z'
            ]
        ];

        return Inertia::render('Admin/IndexView', ['resourceData' => $resourceData, 'resourceNeo' => $this->resourceNeo])->table(function (InertiaTable $table) use ($formInfo) {
            $table->withGlobalSearch();
            foreach (array_keys($formInfo) as $key) {
                $table->column($key, $formInfo[$key]['label'], searchable: $formInfo[$key]['searchable'] ?? false, sortable: $formInfo[$key]['sortable'] ?? false, hidden: $formInfo[$key]['hidden'] ?? false);
            }
            $table->column(label: 'Actions')->perPageOptions([10, 15, 30, 50, 100]);
        });
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $resourceNeo = $this->resourceNeo;
        $resourceNeo['formInfo'] = ConsumableInternalNameGroup::formInfo();
        return Inertia::render('Admin/AddEditView', compact('resourceNeo'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $formInfo = ConsumableInternalNameGroup::formInfo();
        $attributeNames = [];
        $validateRule = [];
        $savedArray = [];
        foreach (array_keys($formInfo) as $key) {
            $attributeNames[$key] = $formInfo[$key]['label'];
            isset($formInfo[$key]['vRule']) && $validateRule[$key] = $formInfo[$key]['vRule'];
            $savedArray[$key] = $request->{$key};
        }
        $request->validate($validateRule, [], $attributeNames);
        $group = ConsumableInternalNameGroup::create($savedArray);

        ActivityLog::add(['action' => 'added', 'module' => $this->resourceNeo['resourceName'], 'data_key' => $request->{array_keys($formInfo)[0]}]);

        if ($request->wantsJson()) {
            return response()->json([
                'message' => 'Created successfully',
                'data' => ConsumableInternalNameGroup::getAllOption()
            ]);
        }

        return redirect()->route('consumableInternalNameGroup.index')->with(['message' => 'Internal name Group Created Successfully !!', 'msg_type' => 'info']);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(ConsumableInternalNameGroup $consumableInternalNameGroup)
    {
        $formdata = $consumableInternalNameGroup;
        $resourceNeo = $this->resourceNeo;
        $resourceNeo['formInfo'] = ConsumableInternalNameGroup::formInfo();
        return Inertia::render('Admin/AddEditView', compact('formdata', 'resourceNeo'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, ConsumableInternalNameGroup $consumableInternalNameGroup)
    {
        $formInfo = ConsumableInternalNameGroup::formInfo();
        $attributeNames = [];
        $validateRule = [];
        foreach (array_keys($formInfo) as $key) {
            $attributeNames[$key] = $formInfo[$key]['label'];
            isset($formInfo[$key]['vRule']) && $validateRule[$key] = $formInfo[$key]['vRule'];
        }
        $validateRule['name'] = 'required|unique:consumable_internal_name_groups,name,' . $consumableInternalNameGroup->id;
        $request->validate($validateRule, [], $attributeNames);
        foreach (array_keys($formInfo) as $key) {
            $consumableInternalNameGroup->{$key} = $request->{$key};
        }

        $consumableInternalNameGroup->save();

        ActivityLog::add(['action' => 'updated', 'module' => $this->resourceNeo['resourceName'], 'data_key' => $request->{array_keys($formInfo)[0]}]);

        return redirect()->route('consumableInternalNameGroup.index')->with(['message' => 'Internal name Group Updated Successfully !!', 'msg_type' => 'info']);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(ConsumableInternalNameGroup $consumableInternalNameGroup)
    {
        $uname = $consumableInternalNameGroup->name;
        $consumableInternalNameGroup->delete();
        ActivityLog::add(['action' => 'deleted', 'module' => 'consumableInternalNameGroup', 'data_key' => $uname]);
        return redirect()->back()->with('message', 'Internal name Group Deleted !!');
    }

    /**
     * bulk delete.
     */
    public function bulkDestroy()
    {
        ConsumableInternalNameGroup::whereIn('id', request('ids'))->delete();
        $uname = (count(request('ids')) > 50) ? 'Many' : implode(',', request('ids'));
        ActivityLog::add(['action' => 'deleted', 'module' => 'consumableInternalNameGroup', 'data_key' => $uname]);
        return redirect()->back()->with('message', 'Selected Internal name Groups Deleted !!');
    }

    /**
     * Show the import view.
     */
    public function importView()
    {
        $this->resourceNeo['extraMainLinks'] = [
            [
                'link' => 'consumableInternalNameGroup.index',
                'label' => 'Back to List',
                'icon' => 'M1,1V5H2V19H1V23H5V22H19V23H23V19H22V5H23V1H19V2H5V1M5,4H19V5H20V19H19V20H5V19H4V5H5M6,6V14H9V18H18V9H14V6M8,8H12V12H8M14,11H16V16H11V14H14'
            ]
        ];

        $sampleData = [
            ['name' => 'Group A'],
            ['name' => 'Group B'],
            ['name' => 'Group C'],
        ];

        $resourceNeo = $this->resourceNeo;

        return Inertia::render('Admin/ImportView', compact('resourceNeo', 'sampleData'));
    }

    /**
     * Import data from a file.
     */
    public function import(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:csv,txt|max:2048' // 2MB limit
        ]);

        $file = $request->file('file');
        $path = $file->getRealPath();

        if (!is_readable($path)) {
            return redirect()->back()->with(['message' => 'Import failed! Unable to read file.', 'msg_type' => 'danger']);
        }

        $records = array_map('str_getcsv', file($path));
        if (empty($records)) {
            return redirect()->back()->with(['message' => 'Import failed! File is empty.', 'msg_type' => 'danger']);
        }

        $headers = array_shift($records);
        $expectedHeaders = ['name'];
        $missingHeaders = array_diff($expectedHeaders, $headers);
        if (!empty($missingHeaders)) {
            return redirect()->back()->with(['message' => 'Import failed! Missing columns: ' . implode(', ', $missingHeaders), 'msg_type' => 'danger']);
        }

        $validatedData = [];
        $errors = [];
        $rowNumber = 2;

        foreach ($records as $record) {
            if (empty(array_filter($record))) {
                $rowNumber++;
                continue;
            }

            $data = array_combine($headers, $record);

            $validator = Validator::make($data, [
                'name' => 'required|unique:consumable_internal_name_groups,name',
            ]);

            if ($validator->fails()) {
                $errors[] = "Row {$rowNumber}: " . implode(', ', $validator->errors()->all());
            } else {
                $validatedData[] = $validator->validated();
            }
            $rowNumber++;
        }

        if (!empty($errors)) {
            return redirect()->back()->with(['message' => implode("\n", $errors), 'msg_type' => 'danger']);
        }

        try {
            DB::beginTransaction();
            foreach ($validatedData as $data) {
                ConsumableInternalNameGroup::create($data);
            }
            DB::commit();
            ActivityLog::add(['action' => 'imported', 'module' => 'consumableInternalNameGroup', 'data_key' => count($validatedData)]);
            return redirect()->route('consumableInternalNameGroup.index')->with(['message' => count($validatedData) . ' records imported!', 'msg_type' => 'success']);
        } catch (\Exception $e) {
            DB::rollback();
            return redirect()->back()->with(['message' => 'Import failed: ' . $e->getMessage(), 'msg_type' => 'danger']);
        }
    }

    /**
     * Get options list.
     */
    public function options()
    {
        return response()->json(ConsumableInternalNameGroup::getAllOption());
    }
}
