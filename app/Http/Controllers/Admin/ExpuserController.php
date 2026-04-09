<?php

namespace App\Http\Controllers\Admin;

use Inertia\Inertia;
use App\Models\Expuser;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use App\Http\Controllers\Controller;
use Spatie\QueryBuilder\QueryBuilder;
use Spatie\QueryBuilder\AllowedFilter;
use ProtoneMedia\LaravelQueryBuilderInertiaJs\InertiaTable;


class ExpuserController extends Controller
{
    protected $resourceNeo = ['resourceName' => 'expuser', 'resourceTitle' => 'Exp. Users', 'iconPath' => 'M12 3C14.21 3 16 4.79 16 7S14.21 11 12 11 8 9.21 8 7 9.79 3 12 3M16 13.54C16 14.6 15.72 17.07 13.81 19.83L13 15L13.94 13.12C13.32 13.05 12.67 13 12 13S10.68 13.05 10.06 13.12L11 15L10.19 19.83C8.28 17.07 8 14.6 8 13.54C5.61 14.24 4 15.5 4 17V21H20V17C20 15.5 18.4 14.24 16 13.54Z', 'actions' => ['c', 'r', 'u', 'd']];

    public function __construct()
    {
        $this->middleware('can:expuser_list', ['only' => ['index', 'show']]);
        $this->middleware('can:expuser_create', ['only' => ['create', 'store', 'importView', 'import']]);
        $this->middleware('can:expuser_delete', ['only' => ['destroy', 'bulkDestroy']]);
        $this->middleware('can:expuser_edit', ['only' => ['edit', 'update']]);
        $this->middleware('can:expuser_delete', ['only' => ['destroy']]);
    }
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $formInfo = Expuser::formInfo();
        $formInfoMulti = [];
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) use ($formInfo, $formInfoMulti) {
            $query->where(function ($query) use ($value, $formInfo, $formInfoMulti) {
                Collection::wrap($value)->each(function ($value) use ($query, $formInfo, $formInfoMulti) {
                    foreach (array_keys($formInfo) as $key) {
                        $query->orWhere($key, $value);
                    }
                    foreach (array_keys($formInfoMulti) as $key) {
                        $query->orWhere($key, $value);
                    }
                });
            });
        });
        $perPage = request()->query('perPage') ?? 10;
        $resourceData = QueryBuilder::for(Expuser::class)
            ->defaultSort('name')
            ->allowedSorts(array_merge(array_keys($formInfo), array_keys($formInfoMulti), []))
            ->allowedFilters(array_merge(array_keys($formInfo), array_keys($formInfoMulti), [$globalSearch]))
            ->paginate($perPage)
            ->withQueryString();

        if (\Auth::user()->can('expuser_delete')) {
            $this->resourceNeo['bulkActions'] = ['bulk_delete' => []];
        }

        if (\Auth::user()->can('expuser_export')) {
            $this->resourceNeo['bulkActions']['csvExport'] = [];
        }


        // Add import link to extraMainLinks
        $this->resourceNeo['extraMainLinks'] = [
            [
                'label' => 'Import',
                'link' => 'expuser.import',
                'icon' => 'M14,12L10,8V11H2V13H10V16M20,18V6C20,4.89 19.1,4 18,4H6A2,2 0 0,0 4,6V9H6V6H18V18H6V15H4V18A2,2 0 0,0 6,20H18A2,2 0 0,0 20,18Z'
            ]
        ];

        return Inertia::render('Admin/IndexView', ['resourceData' => $resourceData, 'resourceNeo' =>
        $this->resourceNeo])->table(function (InertiaTable $table) use ($formInfo, $formInfoMulti) {
            $table->withGlobalSearch();
            $arrKey = array_diff(array_keys($formInfo), []);
            foreach ($arrKey as $key) {
                $table->column($key, $formInfo[$key]['label'], searchable: $formInfo[$key]['searchable'] ?? false, sortable: $formInfo[$key]['sortable'] ?? false, hidden: $formInfo[$key]['hidden'] ?? false);
            }
            foreach (array_keys($formInfoMulti) as $key) {
                $table->column($key, $formInfoMulti[$key]['label'], searchable: $formInfoMulti[$key]['searchable'] ?? false, sortable: $formInfoMulti[$key]['sortable'] ?? false, hidden: $formInfoMulti[$key]['hidden'] ?? false);
            }
            $table
                ->column(label: 'Actions')
                ->perPageOptions([10, 15, 30, 50, 100]);
        });
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $resourceNeo = $this->resourceNeo;
        $resourceNeo['formInfo'] = Expuser::formInfo();
        return Inertia::render('Admin/AddEditView', compact('resourceNeo'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $formInfo = Expuser::formInfo();
        $attributeNames = [];
        $validateRule = [];
        $savedArray = [];
        foreach (array_keys($formInfo) as $key) {
            $attributeNames[$key] = $formInfo[$key]['label'];
            isset($formInfo[$key]['vRule']) && $validateRule[$key] = $formInfo[$key]['vRule'];
            $savedArray[$key] = $request->{$key};
        }
        $request->validate($validateRule, [], $attributeNames);
        Expuser::create($savedArray);

        \ActivityLog::add(['action' => 'added', 'module' => $this->resourceNeo['resourceName'], 'data_key' => $request->{array_keys($formInfo)[0]}]);

        return redirect()->route('expuser.index')->with(['message' => 'Expuser Created Successfully !!', 'msg_type' => 'info']);
    }

    /**
     * Display the specified resource.
     */
    public function show(Expuser $expuser)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Expuser $expuser)
    {
        $formdata = $expuser;
        $resourceNeo = $this->resourceNeo;
        $resourceNeo['formInfo'] = Expuser::formInfo();
        return Inertia::render('Admin/AddEditView', compact('formdata', 'resourceNeo'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Expuser $expuser)
    {
        $formInfo = Expuser::formInfo();
        $attributeNames = [];
        $validateRule = [];
        foreach (array_keys($formInfo) as $key) {
            $attributeNames[$key] = $formInfo[$key]['label'];
            isset($formInfo[$key]['vRule']) && $validateRule[$key] = $formInfo[$key]['vRule'];
        }
        $validateRule['name'] = 'required|unique:expusers,name,' . $expuser->id;
        $request->validate($validateRule, [], $attributeNames);
        foreach (array_diff(array_keys($formInfo), []) as $key) {
            $expuser->{$key} = $request->{$key};
        }

        $expuser->save();

        return redirect()->route('expuser.index')->with(['message' => 'Expuser Updated Successfully !!', 'msg_type' => 'info']);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Expuser $expuser)
    {
        $uname = $expuser->id;
        $expuser->delete();
        \ActivityLog::add(['action' => 'deleted', 'module' => 'expuser', 'data_key' => $uname]);
        return redirect()->back()->with('message', 'Expuser Deleted !!');
    }

    /**
     * bulk delete.
     */
    public function bulkDestroy()
    {
        Expuser::whereIn('id', request('ids'))->delete();
        $uname = (count(request('ids')) > 50) ? 'Many' : $uname = implode(',', request('ids'));
        \ActivityLog::add(['action' => 'deleted', 'module' => 'expuser', 'data_key' => $uname]);
        return redirect()->back()->with('message', 'Selected Expuser Deleted !!');
    }
    /**
     * Show the import view.
     */
    public function importView()
    {
        $this->resourceNeo['extraMainLinks'] = [
            [
                'link' => 'expuser.index',
                'label' => 'Back to List',
                'icon' => 'M12 3C14.21 3 16 4.79 16 7S14.21 11 12 11 8 9.21 8 7 9.79 3 12 3M16 13.54C16 14.6 15.72 17.07 13.81 19.83L13 15L13.94 13.12C13.32 13.05 12.67 13 12 13S10.68 13.05 10.06 13.12L11 15L10.19 19.83C8.28 17.07 8 14.6 8 13.54C5.61 14.24 4 15.5 4 17V21H20V17C20 15.5 18.4 14.24 16 13.54Z'
            ]
        ];

        $sampleData = [
            ['name' => 'John Doe'],
            ['name' => 'Jane Smith'],
            ['name' => 'Robert Johnson'],
            ['name' => 'Emily Davis'],
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

        // Check if file is readable
        if (!is_readable($path)) {
            return redirect()->back()
                ->with([
                    'message' => 'Import failed! Unable to read the uploaded file.',
                    'msg_type' => 'danger'
                ]);
        }

        $records = array_map('str_getcsv', file($path));

        // Check if file has content
        if (empty($records)) {
            return redirect()->back()
                ->with([
                    'message' => 'Import failed! The uploaded file is empty.',
                    'msg_type' => 'danger'
                ]);
        }

        // Remove header row
        $headers = array_shift($records);

        // Validate headers
        $expectedHeaders = ['name'];
        $missingHeaders = array_diff($expectedHeaders, $headers);
        if (!empty($missingHeaders)) {
            return redirect()->back()
                ->with([
                    'message' => 'Import failed! Missing required columns: ' . implode(', ', $missingHeaders),
                    'msg_type' => 'danger'
                ]);
        }

        // Check if there are data rows
        if (empty($records)) {
            return redirect()->back()
                ->with([
                    'message' => 'Import failed! No data rows found in the file.',
                    'msg_type' => 'danger'
                ]);
        }

        // Validate all records first
        $errors = [];
        $rowNumber = 2; // Starting from 2 as row 1 is headers
        $validatedData = [];

        foreach ($records as $record) {
            // Skip empty rows
            if (empty(array_filter($record))) {
                $rowNumber++;
                continue;
            }

            $data = array_combine($headers, $record);

            // Create validator with same rules as store function
            $validator = \Validator::make($data, [
                'name' => 'required|unique:expusers,name',
            ], [
                'name.required' => 'Expuser Name is required',
                'name.unique' => 'Expuser Name already exists',
            ]);

            if ($validator->fails()) {
                $errors[] = "Row {$rowNumber}: " . implode(', ', $validator->errors()->all());
            } else {
                $validatedData[] = $validator->validated();
            }

            $rowNumber++;
        }

        // If there are any validation errors, redirect back with errors
        if (!empty($errors)) {
            $errorMessage = "Import failed! Please fix the following errors:\n\n" . implode("\n", $errors);
            return redirect()->back()
                ->with([
                    'message' => $errorMessage,
                    'msg_type' => 'danger'
                ]);
        }

        // If validation passed for all records, proceed with import using transaction
        try {
            \DB::beginTransaction();

            foreach ($validatedData as $data) {
                Expuser::create($data);
            }

            \DB::commit();
            \ActivityLog::add(['action' => 'imported', 'module' => 'expuser', 'data_key' => count($validatedData)]);

            return redirect()->route('expuser.index')
                ->with([
                    'message' => count($validatedData) . ' expuser records imported successfully!',
                    'msg_type' => 'success'
                ]);
        } catch (\Exception $e) {
            \DB::rollback();
            \Log::error('Expuser import failed: ' . $e->getMessage());
            return redirect()->back()
                ->with([
                    'message' => 'Import failed! An unexpected error occurred while importing the data. Please try again.',
                    'msg_type' => 'danger'
                ]);
        }
    }
}
