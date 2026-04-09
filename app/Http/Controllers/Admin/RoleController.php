<?php

namespace App\Http\Controllers\Admin;

use App\Models\Role;
use Inertia\Inertia;
use App\Models\Permission;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Spatie\QueryBuilder\QueryBuilder;
use Spatie\QueryBuilder\AllowedFilter;
use ProtoneMedia\LaravelQueryBuilderInertiaJs\InertiaTable;

class RoleController extends Controller
{
    protected $resourceNeo = ['resourceName' => 'role', 'resourceTitle' => 'Role', 'iconPath' => 'M12,5.5A3.5,3.5 0 0,1 15.5,9A3.5,3.5 0 0,1 12,12.5A3.5,3.5 0 0,1 8.5,9A3.5,3.5 0 0,1 12,5.5M5,8C5.56,8 6.08,8.15 6.53,8.42C6.38,9.85 6.8,11.27 7.66,12.38C7.16,13.34 6.16,14 5,14A3,3 0 0,1 2,11A3,3 0 0,1 5,8M19,8A3,3 0 0,1 22,11A3,3 0 0,1 19,14C17.84,14 16.84,13.34 16.34,12.38C17.2,11.27 17.62,9.85 17.47,8.42C17.92,8.15 18.44,8 19,8M5.5,18.25C5.5,16.18 8.41,14.5 12,14.5C15.59,14.5 18.5,16.18 18.5,18.25V20H5.5V18.25M0,20V18.5C0,17.11 1.89,15.94 4.45,15.6C3.86,16.28 3.5,17.22 3.5,18.25V20H0M24,20H20.5V18.25C20.5,17.22 20.14,16.28 19.55,15.6C22.11,15.94 24,17.11 24,18.5V20Z', 'actions' => ['c', 'r', 'u', 'd']];

    public function __construct()
    {
        $this->middleware('can:role_list', ['only' => ['index', 'show']]);
        $this->middleware('can:role_create', ['only' => ['create', 'store']]);
        $this->middleware('can:role_edit', ['only' => ['edit', 'update']]);
        $this->middleware('can:role_delete', ['only' => ['destroy']]);
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $query = Role::where('id', '<>', 3);

        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                Collection::wrap($value)->each(function ($value) use ($query) {
                    $query
                        ->orWhere('roles.name', 'LIKE', "%{$value}%");

                });
            });
        });
        $perPage = request()->query('perPage') ?? 10;
        $roles = QueryBuilder::for($query)
            ->defaultSort('name')
            ->allowedSorts(['name'])
            ->allowedFilters(['name', $globalSearch])
            ->paginate($perPage)
            ->withQueryString();



        if (Auth::user()->can('role_export')) {
            $this->resourceNeo['bulkActions']['csvExport'] = [];
        }

        return Inertia::render('Admin/IndexView', ['resourceData' => $roles, 'resourceNeo' => $this->resourceNeo])->table(function (InertiaTable $table) {
            $table->withGlobalSearch()
                ->column('name', 'Name', searchable: false, sortable: true, )

                ->column(label: 'Actions')
                ->perPageOptions([10, 15, 30, 50, 100]);
        });
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        $allperm_t = Permission::orderBy('name')->get();
        $allpermissions = [];
        $allmodules = config('app.modules'); // ['module_key' => 'Readable']
        $moduleKeys = array_keys($allmodules); // preserve config order

        // Initialize buckets
        foreach ($moduleKeys as $mKey) {
            $allpermissions[$mKey] = ['sts' => false, 'child' => [], 'name' => $allmodules[$mKey] ?? ucfirst($mKey)];
        }

        // Fill actions
        foreach ($allperm_t as $perm) {
            $name = $perm->name;
            if (str_starts_with($name, 'permission')) {
                continue;
            }
            foreach ($moduleKeys as $mKey) {
                $prefix = $mKey . '_';
                if (str_starts_with($name, $prefix)) {
                    $action = substr($name, strlen($prefix));
                    if ($action !== '' && $action !== false) {
                        $allpermissions[$mKey]['child'][] = [$perm->id, $action, false];
                    }
                    break;
                }
            }
        }

        // Remove modules with no actions, but keep keys
        $allpermissions = array_filter($allpermissions, function ($group) {
            return !empty($group['child']);
        });

        return Inertia::render('Admin/RoleAddEditView', compact('allpermissions'));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
        ]);

        $role = Role::create(request(['name']));

        foreach ($request->permission as $grouppermssion) {
            foreach ($grouppermssion['child'] as $permdata) {
                if ($permdata[2]) {
                    $permission = Permission::findById($permdata[0]);
                    $role->givePermissionTo($permission);
                }
            }
        }
        $uname = $request->input('name');
        \ActivityLog::add(['action' => 'created', 'module' => 'role', 'data_key' => $uname]);

        return redirect()->route('role.index')->with(['message' => 'Role Created Successfully', 'msg_type' => 'success']);
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        $formdata = Role::where('id', $id)->with('permissions:id')->get()->first();

        $allgivenpermission = [];

        foreach ($formdata->permissions as $key => $value) {
            $allgivenpermission[] = $value->id;
        }

        $allperm_t = Permission::orderBy('name')->get();
        $allpermissions = [];
        $allmodules = config('app.modules');
        $moduleKeys = array_keys($allmodules); // preserve config order

        // Initialize buckets
        foreach ($moduleKeys as $mKey) {
            $allpermissions[$mKey] = ['sts' => false, 'child' => [], 'name' => $allmodules[$mKey] ?? ucfirst($mKey)];
        }

        // Fill actions
        foreach ($allperm_t as $perm) {
            $name = $perm->name;
            if (str_starts_with($name, 'permission')) {
                continue;
            }
            foreach ($moduleKeys as $mKey) {
                $prefix = $mKey . '_';
                if (str_starts_with($name, $prefix)) {
                    $action = substr($name, strlen($prefix));
                    if ($action !== '' && $action !== false) {
                        $isChecked = in_array($perm->id, $allgivenpermission, true);
                        $allpermissions[$mKey]['child'][] = [$perm->id, $action, $isChecked];
                        $allpermissions[$mKey]['sts'] = $allpermissions[$mKey]['sts'] || $isChecked;
                    }
                    break;
                }
            }
        }

        // Remove modules with no actions, but keep keys
        $allpermissions = array_filter($allpermissions, function ($group) {
            return !empty($group['child']);
        });

        return Inertia::render('Admin/RoleAddEditView', compact('formdata', 'allpermissions'));
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */

    public function update(Request $request, Role $role)
    {
        $request->validate([
            'name' => 'required|string|max:255',
        ]);
        $isDirty = false;

        $role->name = $request->name;
        $role->guard_name = 'web';
        if ($role->isDirty()) {
            $role->save();
            $isDirty = true;
        }

        $formdata = Role::where('id', $role->id)->with('permissions:id')->get()->first();
        $allgivenpermission = [];
        foreach ($formdata->permissions as $key => $value) {
            $allgivenpermission[] = $value->id;
        }
        $allnewpermission = [];

        $role->permissions()->detach();
        foreach ($request->permission as $grouppermssion) {
            foreach ($grouppermssion['child'] as $permdata) {
                if ($permdata[2]) {
                    $permission = Permission::findById($permdata[0]);
                    $allnewpermission[] = $permdata[0];
                    $role->givePermissionTo($permission);
                }
            }
        }
        sort($allnewpermission);
        sort($allgivenpermission);

        if ($allnewpermission != $allgivenpermission) {
            $isDirty = true;
        }
        if ($isDirty) {
            $uname = $role->name;
            \ActivityLog::add(['action' => 'updated', 'module' => 'role', 'data_key' => $uname]);
            $res = ['message' => 'Role Updated Successfully.', 'msg_type' => 'success'];
        } else {
            $res = ['message' => 'No Value  Updated in Role .', 'msg_type' => 'warning'];
        }
        return redirect()->route('role.index')->with($res);
    }


    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy(Role $role)
    {
        $users = $role->users()->get();

        if (count($users) > 0) {
            return redirect()->route('role.index')->with(['message' => 'Can\'t delete role that has users associated, first dissociate user with this role !!', 'msg_type' => 'danger']);
        }
        $uname = $role->name;
        $role->delete();
        \ActivityLog::add(['action' => 'deleted', 'module' => 'role', 'data_key' => $uname]);
        return redirect()->route('role.index')->with('message', 'Role Deleted !!');
    }
}
