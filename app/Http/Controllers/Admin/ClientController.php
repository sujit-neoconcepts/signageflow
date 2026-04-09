<?php

namespace App\Http\Controllers\Admin;

use Inertia\Inertia;
use App\Models\Client;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Spatie\QueryBuilder\AllowedSort;
use Spatie\QueryBuilder\QueryBuilder;
use Spatie\QueryBuilder\AllowedFilter;
use ProtoneMedia\LaravelQueryBuilderInertiaJs\InertiaTable;

use App\Helpers\Helper;

class ClientController extends Controller
{
    protected $resourceNeo = ['resourceName' => 'client', 'resourceTitle' => 'Clients', 'iconPath' => 'M12 3C14.21 3 16 4.79 16 7S14.21 11 12 11 8 9.21 8 7 9.79 3 12 3M16 13.54C16 14.6 15.72 17.07 13.81 19.83L13 15L13.94 13.12C13.32 13.05 12.67 13 12 13S10.68 13.05 10.06 13.12L11 15L10.19 19.83C8.28 17.07 8 14.6 8 13.54C5.61 14.24 4 15.5 4 17V21H20V17C20 15.5 18.4 14.24 16 13.54Z', 'actions' => ['c', 'r', 'u', 'd']];

    public function __construct()
    {
        $this->middleware('can:client_list', ['only' => ['index', 'show']]);
        $this->middleware('can:client_create', ['only' => ['create', 'store', 'importView', 'import']]);
        $this->middleware('can:client_delete', ['only' => ['destroy', 'bulkDestroy']]);
        $this->middleware('can:client_edit', ['only' => ['edit', 'update']]);
        $this->middleware('can:client_delete', ['only' => ['destroy']]);
    }

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $formInfo = Client::formInfo();
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) use ($formInfo) {
            $query->where(function ($query) use ($value, $formInfo) {
                Collection::wrap($value)->each(function ($value) use ($query, $formInfo) {
                    foreach (array_keys($formInfo) as $key) {
                        $query->orWhere($key, 'LIKE', "%{$value}%");
                    }
                });
            });
        });


        $perPage = request()->query('perPage') ?? 10;
        $resourceData = QueryBuilder::for(Client::class)
            ->defaultSort('cl_name')
            ->allowedSorts(array_merge(array_keys($formInfo), ['id', AllowedSort::field('active_status', 'active')]))
            ->allowedFilters(array_merge(array_keys($formInfo), [$globalSearch]))
            ->paginate($perPage)
            ->withQueryString();
        $this->resourceNeo['bulkActions'] = [];

        if (Auth::user()->can('client_delete')) {
            $this->resourceNeo['bulkActions']['bulk_delete'] = [];
        }

        if (Auth::user()->can('client_export')) {
            $this->resourceNeo['bulkActions']['csvExport'] = [];
        }

        // Add import link to extraMainLinks
        $this->resourceNeo['extraMainLinks'] = [
            [
                'label' => 'Import',
                'link' => 'client.import',
                'icon' => 'M14,12L10,8V11H2V13H10V16M20,18V6C20,4.89 19.1,4 18,4H6A2,2 0 0,0 4,6V9H6V6H18V18H6V15H4V18A2,2 0 0,0 6,20H18A2,2 0 0,0 20,18Z'
            ]
        ];

        return Inertia::render('Admin/IndexView', ['resourceData' => $resourceData, 'resourceNeo' => $this->resourceNeo])->table(function (InertiaTable $table) use ($formInfo) {
            $table->withGlobalSearch();

           // $table->column('id', 'ID', sortable: true);
            $arrKey = array_diff(array_keys($formInfo), ['password', 'active']);
            foreach ($arrKey as $key) {
                $table->column($key, $formInfo[$key]['label'], searchable: true, sortable: true);
            }
            $table->column('active_status', 'Active', sortable: true);
            $table
                ->column(label: 'Actions')
                ->perPageOptions([10, 15, 30, 50, 100, 10000]);
        });
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $resourceNeo = $this->resourceNeo;
        $resourceNeo['formInfo'] = Client::formInfo();



        return Inertia::render('Admin/AddEditView', compact('resourceNeo'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $formInfo = Client::formInfo();
        $attributeNames = [];
        $validateRule = [];
        $savedArray = [];
        foreach (array_keys($formInfo) as $key) {
            $attributeNames[$key] = $formInfo[$key]['label'];
            isset($formInfo[$key]['vRule']) && $validateRule[$key] = $formInfo[$key]['vRule'];
            $savedArray[$key] = $request->{$key};
        }


        $savedArray['active'] = $request->active['id'] ?? 0;
        $request->validate($validateRule, [], $attributeNames);


        if (!empty($savedArray['cl_name'])) {
            $savedArray['cl_name'] = Helper::ucfirstlower($savedArray['cl_name']);
        }
        if (!empty($savedArray['contact_person'])) {
            $savedArray['contact_person'] = Helper::ucfirstlower($savedArray['contact_person']);
        }
        if (!empty($savedArray['cl_addr'])) {
            $savedArray['cl_addr'] = Helper::ucfirstlower($savedArray['cl_addr']);
        }
        if (!empty($savedArray['cl_addr2'])) {
            $savedArray['cl_addr2'] = Helper::ucfirstlower($savedArray['cl_addr2']);
        }


        Client::create($savedArray);

        \ActivityLog::add(['action' => 'added', 'module' => $this->resourceNeo['resourceName'], 'data_key' => $request->{array_keys($formInfo)[0]}]);

        return redirect()->route('client.index')->with(['message' => $this->resourceNeo['resourceTitle'] . ' Created Successfully !!', 'msg_type' => 'info']);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Client $client)
    {
        $formdata = $client;
        $formdata->active = ['id' => $client->active, 'label' => $client->active ? 'Yes' : 'No'];

        $resourceNeo = $this->resourceNeo;
        $resourceNeo['formInfo'] = Client::formInfo();



        return Inertia::render('Admin/AddEditView', compact('formdata', 'resourceNeo'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Client $client)
    {
        $formInfo = Client::formInfo();
        $attributeNames = [];
        $validateRule = [];
        foreach (array_keys($formInfo) as $key) {
            $attributeNames[$key] = $formInfo[$key]['label'];
            isset($formInfo[$key]['vRule']) && $validateRule[$key] = $formInfo[$key]['vRule'];
        }
        $validateRule['cl_name'] = 'required|unique:clients,cl_name,' . $client->id;
        $request->validate($validateRule, [], $attributeNames);


        foreach (array_keys($formInfo) as $key) {
            $client->{$key} = $request->{$key};
        }



        $client->active = $request->active['id'];

        $client->save();

        \ActivityLog::add(['action' => 'updated', 'module' => $this->resourceNeo['resourceName'], 'data_key' => $request->{array_keys($formInfo)[0]}]);

        return redirect()->route('client.index')->with(['message' => $this->resourceNeo['resourceTitle'] . ' Updated Successfully !!', 'msg_type' => 'info']);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Client $client)
    {
        $formInfo = Client::formInfo();
        $uname = $client->{array_keys($formInfo)[0]};
        $client->delete();

        \ActivityLog::add(['action' => 'deleted', 'module' => $this->resourceNeo['resourceName'], 'data_key' => $uname]);
        return redirect()->back()->with('message', $this->resourceNeo['resourceTitle'] . ' Deleted !!');
    }

    /**
     * bulk delete.
     */
    public function bulkDestroy()
    {
        Client::whereIn('id', request('ids'))->delete();
        $uname = (count(request('ids')) > 50) ? 'Many' : $uname = implode(',', request('ids'));
        \ActivityLog::add(['action' => 'deleted', 'module' => $this->resourceNeo['resourceName'], 'data_key' => $uname]);
        return redirect()->back()->with('message', 'Selected ' . $this->resourceNeo['resourceTitle'] . ' Deleted !!');
    }

    /**
     * Show the import view.
     */
    public function importView()
    {
        $this->resourceNeo['extraMainLinks'] = [
            [
                'link' => 'client.index',
                'label' => 'Back to List',
                'icon' => 'M12 3C14.21 3 16 4.79 16 7S14.21 11 12 11 8 9.21 8 7 9.79 3 12 3M16 13.54C16 14.6 15.72 17.07 13.81 19.83L13 15L13.94 13.12C13.32 13.05 12.67 13 12 13S10.68 13.05 10.06 13.12L11 15L10.19 19.83C8.28 17.07 8 14.6 8 13.54C5.61 14.24 4 15.5 4 17V21H20V17C20 15.5 18.4 14.24 16 13.54Z'
            ]
        ];

        $sampleData = [
            ['cl_name' => 'ABC Industries', 'contact_person' => 'John Smith', 'cl_addr' => '123 Business Street', 'cl_addr2' => 'Suite 100', 'pincode' => '400001', 'cl_phn' => '9876543210', 'cl_email' => 'john@abcindustries.com', 'password' => 'password123', 'cl_gst' => '27ABCDE1234F1Z5'],
            ['cl_name' => 'XYZ Corporation', 'contact_person' => 'Jane Doe', 'cl_addr' => '456 Corporate Ave', 'cl_addr2' => 'Floor 5', 'pincode' => '400002', 'cl_phn' => '9876543211', 'cl_email' => 'jane@xyzcorp.com', 'password' => 'password123', 'cl_gst' => '29XYZAB5678G2W6'],
            ['cl_name' => 'Tech Solutions Ltd', 'contact_person' => 'Mike Johnson', 'cl_addr' => '789 Tech Park', 'cl_addr2' => 'Building A', 'pincode' => '400003', 'cl_phn' => '9876543212', 'cl_email' => 'mike@techsolutions.com', 'password' => 'password123', 'cl_gst' => '24TECH9012H3X7'],
            ['cl_name' => 'Global Enterprises', 'contact_person' => 'Sarah Wilson', 'cl_addr' => '321 Global Plaza', 'cl_addr2' => 'Tower B', 'pincode' => '400004', 'cl_phn' => '9876543213', 'cl_email' => 'sarah@globalent.com', 'password' => 'password123', 'cl_gst' => '33GLOBAL3456I4Y8'],
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
        // Trim headers
        $headers = array_map('trim', $headers);

        // Validate headers - only required fields are mandatory, others are ignored if present
        $requiredHeaders = ['cl_name', 'contact_person', 'cl_addr', 'cl_addr2', 'pincode', 'cl_phn', 'cl_email', 'password', 'cl_gst'];
        $missingRequiredHeaders = array_diff($requiredHeaders, $headers);
        if (!empty($missingRequiredHeaders)) {
            return redirect()->back()
                ->with([
                    'message' => 'Import failed! Missing required columns: ' . implode(', ', $missingRequiredHeaders),
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
        $formInfo = Client::formInfo();

        foreach ($records as $record) {
            // Skip empty rows
            if (empty(array_filter($record))) {
                $rowNumber++;
                continue;
            }

            $data = array_combine($headers, $record);

            // Force active status to 1 for all imports
            $data['active'] = 1;

            // Handle empty values - convert empty strings to null for proper database storage
            foreach ($data as $key => $value) {
                if ($value === '' && !in_array($key, ['password', 'active'])) {
                    $data[$key] = null;
                }
            }

            // Handle password field specifically - if empty, set a default password
            if (isset($data['password']) && $data['password'] === '') {
                $data['password'] = 'password123'; // Default password for empty fields
            }


            // Build validation rules using the same logic as store method
            $attributeNames = [];
            $validationRules = [];
            $savedArray = [];

            foreach (array_keys($formInfo) as $key) {
                $attributeNames[$key] = $formInfo[$key]['label'];
                if (isset($formInfo[$key]['vRule'])) {
                    $validationRules[$key] = $formInfo[$key]['vRule'];
                }
                // Only include fields that exist in the data
                if (array_key_exists($key, $data)) {
                    $savedArray[$key] = $data[$key];
                }
            }

            $validator = \Validator::make($savedArray, $validationRules, [], $attributeNames);

            if ($validator->fails()) {
                $errors[] = "Row {$rowNumber}: " . implode(', ', $validator->errors()->all());
            } else {
                $validatedData[] = $savedArray;
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

                if (!empty($data['cl_name'])) {
                    $data['cl_name'] = Helper::ucfirstlower($data['cl_name']);
                }
                if (!empty($data['contact_person'])) {
                    $data['contact_person'] = Helper::ucfirstlower($data['contact_person']);
                }
                if (!empty($data['cl_addr'])) {
                    $data['cl_addr'] = Helper::ucfirstlower($data['cl_addr']);
                }
                if (!empty($data['cl_addr2'])) {
                    $data['cl_addr2'] = Helper::ucfirstlower($data['cl_addr2']);
                }

                Client::create($data);
            }

            \DB::commit();
            \ActivityLog::add(['action' => 'imported', 'module' => 'client', 'data_key' => count($validatedData)]);


            return redirect()->route('client.index')
                ->with([
                    'message' => count($validatedData) . ' client records imported successfully!',
                    'msg_type' => 'success'
                ]);
        } catch (\Exception $e) {
            \DB::rollback();
            \Log::error('Client import failed: ' . $e->getMessage());
            return redirect()->back()
                ->with([
                    'message' => 'Import failed! An unexpected error occurred while importing the data. Please try again.',
                    'msg_type' => 'danger'
                ]);
        }
    }
}
