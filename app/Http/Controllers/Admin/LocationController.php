<?php

namespace App\Http\Controllers\Admin;

use App\Models\Location;
use Inertia\Inertia;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use App\Http\Controllers\Controller;
use Spatie\QueryBuilder\QueryBuilder;
use Spatie\QueryBuilder\AllowedFilter;
use ProtoneMedia\LaravelQueryBuilderInertiaJs\InertiaTable;

class LocationController extends Controller
{
    protected $resourceNeo = ['resourceName' => 'location', 'resourceTitle' => 'Locations', 'iconPath' => 'M11.5 9C11.5 7.62 12.62 6.5 14 6.5C15.1 6.5 16.03 7.21 16.37 8.19C16.45 8.45 16.5 8.72 16.5 9C16.5 10.38 15.38 11.5 14 11.5C12.91 11.5 12 10.81 11.64 9.84C11.55 9.58 11.5 9.29 11.5 9M5 9C5 13.5 10.08 19.66 11 20.81L10 22C10 22 3 14.25 3 9C3 5.83 5.11 3.15 8 2.29C6.16 3.94 5 6.33 5 9M14 2C17.86 2 21 5.13 21 9C21 14.25 14 22 14 22C14 22 7 14.25 7 9C7 5.13 10.14 2 14 2M14 4C11.24 4 9 6.24 9 9C9 10 9 12 14 18.71C19 12 19 10 19 9C19 6.24 16.76 4 14 4Z', 'actions' => ['c', 'r', 'u', 'd']];

    public function __construct()
    {
        $this->middleware('can:location_list', ['only' => ['index', 'show']]);
        $this->middleware('can:location_create', ['only' => ['create', 'store', 'importView', 'import']]);
        $this->middleware('can:location_delete', ['only' => ['destroy', 'bulkDestroy']]);
        $this->middleware('can:location_edit', ['only' => ['edit', 'update']]);
        $this->middleware('can:location_delete', ['only' => ['destroy']]);
    }
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $formInfo = Location::formInfo();
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
        $resourceData = QueryBuilder::for(Location::class)
            ->defaultSort('name')
            ->allowedSorts(array_merge(array_keys($formInfo), array_keys($formInfoMulti), []))
            ->allowedFilters(array_merge(array_keys($formInfo), array_keys($formInfoMulti), [$globalSearch]))
            ->paginate($perPage)
            ->withQueryString();

        if (\Auth::user()->can('location_delete')) {
            $this->resourceNeo['bulkActions'] = ['bulk_delete' => []];
        }

        if (\Auth::user()->can('location_export')) {
            $this->resourceNeo['bulkActions']['csvExport'] = [];
        }


        // Add import link to extraMainLinks
        $this->resourceNeo['extraMainLinks'] = [
            [
                'label' => 'Import',
                'link' => 'location.import',
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
        $resourceNeo['formInfo'] = Location::formInfo();
        return Inertia::render('Admin/AddEditView', compact('resourceNeo'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $formInfo = Location::formInfo();
        $attributeNames = [];
        $validateRule = [];
        $savedArray = [];
        foreach (array_keys($formInfo) as $key) {
            $attributeNames[$key] = $formInfo[$key]['label'];
            isset($formInfo[$key]['vRule']) && $validateRule[$key] = $formInfo[$key]['vRule'];
            $savedArray[$key] = $request->{$key};
        }
        $request->validate($validateRule, [], $attributeNames);
        Location::create($savedArray);

        \ActivityLog::add(['action' => 'added', 'module' => $this->resourceNeo['resourceName'], 'data_key' => $request->{array_keys($formInfo)[0]}]);

        return redirect()->route('location.index')->with(['message' => 'Location Created Successfully !!', 'msg_type' => 'info']);
    }

    /**
     * Display the specified resource.
     */
    public function show(Location $location)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Location $location)
    {
        $formdata = $location;
        $resourceNeo = $this->resourceNeo;
        $resourceNeo['formInfo'] = Location::formInfo();
        return Inertia::render('Admin/AddEditView', compact('formdata', 'resourceNeo'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Location $location)
    {
        $formInfo = Location::formInfo();
        $attributeNames = [];
        $validateRule = [];
        foreach (array_keys($formInfo) as $key) {
            $attributeNames[$key] = $formInfo[$key]['label'];
            isset($formInfo[$key]['vRule']) && $validateRule[$key] = $formInfo[$key]['vRule'];
        }
        $validateRule['name'] = 'required|unique:locations,name,' . $location->id;
        $request->validate($validateRule, [], $attributeNames);
        foreach (array_diff(array_keys($formInfo), []) as $key) {
            $location->{$key} = $request->{$key};
        }

        $location->save();

        return redirect()->route('location.index')->with(['message' => 'Location Updated Successfully !!', 'msg_type' => 'info']);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Location $location)
    {
        $uname = $location->id;
        $location->delete();
        \ActivityLog::add(['action' => 'deleted', 'module' => 'location', 'data_key' => $uname]);
        return redirect()->back()->with('message', 'Location Deleted !!');
    }

    /**
     * bulk delete.
     */
    public function bulkDestroy()
    {
        Location::whereIn('id', request('ids'))->delete();
        $uname = (count(request('ids')) > 50) ? 'Many' : $uname = implode(',', request('ids'));
        \ActivityLog::add(['action' => 'deleted', 'module' => 'location', 'data_key' => $uname]);
        return redirect()->back()->with('message', 'Selected Location Deleted !!');
    }
    /**
     * Show the import view.
     */
    public function importView()
    {
        $this->resourceNeo['extraMainLinks'] = [
            [
                'link' => 'location.index',
                'label' => 'Back to List',
                'icon' => 'M11.5 9C11.5 7.62 12.62 6.5 14 6.5C15.1 6.5 16.03 7.21 16.37 8.19C16.45 8.45 16.5 8.72 16.5 9C16.5 10.38 15.38 11.5 14 11.5C12.91 11.5 12 10.81 11.64 9.84C11.55 9.58 11.5 9.29 11.5 9M5 9C5 13.5 10.08 19.66 11 20.81L10 22C10 22 3 14.25 3 9C3 5.83 5.11 3.15 8 2.29C6.16 3.94 5 6.33 5 9M14 2C17.86 2 21 5.13 21 9C21 14.25 14 22 14 22C14 22 7 14.25 7 9C7 5.13 10.14 2 14 2M14 4C11.24 4 9 6.24 9 9C9 10 9 12 14 18.71C19 12 19 10 19 9C19 6.24 16.76 4 14 4Z'
            ]
        ];

        $sampleData = [
            ['name' => 'Store'],
            ['name' => 'Main Office'],
            ['name' => 'Store Room 1'],
            ['name' => 'Store Room 2'],
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
                'name' => 'required|unique:locations,name',
            ], [
                'name.required' => 'Location Name is required',
                'name.unique' => 'Location Name already exists',
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
                Location::create($data);
            }

            \DB::commit();
            \ActivityLog::add(['action' => 'imported', 'module' => 'location', 'data_key' => count($validatedData)]);

            return redirect()->route('location.index')
                ->with([
                    'message' => count($validatedData) . ' location records imported successfully!',
                    'msg_type' => 'success'
                ]);
        } catch (\Exception $e) {
            \DB::rollback();
            \Log::error('Location import failed: ' . $e->getMessage());
            return redirect()->back()
                ->with([
                    'message' => 'Import failed! An unexpected error occurred while importing the data. Please try again.',
                    'msg_type' => 'danger'
                ]);
        }
    }
}
