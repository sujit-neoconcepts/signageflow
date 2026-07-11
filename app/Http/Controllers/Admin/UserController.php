<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\User;
use App\Rules\NotUsedPassword;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Session;
use Inertia\Inertia;
use ProtoneMedia\LaravelQueryBuilderInertiaJs\InertiaTable;
use Spatie\Permission\Models\Permission;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class UserController extends Controller
{
    protected $resourceNeo = ['resourceName' => 'user', 'resourceTitle' => 'Users', 'iconPath' => 'M4,6H2V20A2,2 0 0,0 4,22H18V20H4V6M20,2A2,2 0 0,1 22,4V16A2,2 0 0,1 20,18H8A2,2 0 0,1 6,16V4A2,2 0 0,1 8,2H20M17,7A3,3 0 0,0 14,4A3,3 0 0,0 11,7A3,3 0 0,0 14,10A3,3 0 0,0 17,7M8,15V16H20V15C20,13 16,11.9 14,11.9C12,11.9 8,13 8,15Z'];

    public function __construct()
    {
        $this->middleware('can:user_list', ['only' => ['index', 'show']]);
        $this->middleware('can:user_create', ['only' => ['create', 'store', 'storeExecutive']]);
        $this->middleware('can:user_edit', ['only' => ['edit', 'update']]);
        $this->middleware('can:user_delete', ['only' => ['destroy']]);
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $query = User::query();

        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                Collection::wrap($value)->each(function ($value) use ($query) {
                    $query
                        ->orWhere('users.name', 'LIKE', "%{$value}%")
                        ->orWhere('email', 'LIKE', "%{$value}%");

                });
            });
        });
        $perPage = request()->query('perPage') ?? 10;
        $users = QueryBuilder::for($query)
            ->with('roles')
            ->defaultSort('name')
            ->allowedSorts(['name', 'email'])
            ->allowedFilters(['name', 'email', $globalSearch])
            ->paginate($perPage)
            ->withQueryString();

        // Append role_name and is_super_admin to each user for frontend display
        $users->getCollection()->transform(function ($user) {
            $user->append(['role_name', 'is_super_admin']);

            return $user;
        });

        if (Auth::user()->can('user_export')) {
            $this->resourceNeo['bulkActions']['csvExport'] = [];
        }

        return Inertia::render('Admin/UserIndexView', ['users' => $users, 'resourceNeo' => $this->resourceNeo])->table(function (InertiaTable $table) {
            $table->withGlobalSearch()
                ->column('name', 'Name', searchable: true, sortable: true)
                ->column('email', 'Email', searchable: true, sortable: true)
                ->column('role_name', 'Role', searchable: false, sortable: false)

                ->column(label: 'Actions')
                ->perPageOptions([10, 15, 30, 50, 100]);
        });
    }

    public function profile()
    {
        $formdata = auth()->user();

        return Inertia::render('Admin/ProfileView', compact('formdata'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        $roles = Role::select('id', 'name as label')->where('name', '<>', 'super-admin')->get();

        return Inertia::render('Admin/UserAddEditView', compact('roles'));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $validationRules = [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|unique:users,email',
            'phone' => 'nullable|string|max:20',
            'password' => 'required|string|min:8',
            'roles' => 'required|array|min:1',
            'roles.*.id' => 'required|exists:roles,id',
        ];

        $request->validate($validationRules);

        $roleIds = collect($request->roles)->pluck('id')->all();

        // Enforce exclusivity of super-admin role
        $hasSuperAdmin = Role::whereIn('id', $roleIds)->where('name', 'super-admin')->exists();
        if ($hasSuperAdmin) {
            return redirect()->back()->withErrors(['roles' => 'The super-admin role cannot be assigned.'])->withInput();
        }

        $roles = Role::whereIn('id', $roleIds)->get();

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'phone' => $request->phone,
            'password' => $request->password,
            'twofa' => $request->twofa,
        ]);
        $user->assignRole($roles);

        $uname = $request->input('name').'-'.$roles->pluck('name')->implode(', ');
        \ActivityLog::add(['action' => 'created', 'module' => 'user', 'data_key' => $uname]);

        User::neUserMail(
            [
                'password' => $request->input('password'),
                'ip' => $request->ip(),
                'user' => $user,
                'userAgent' => $request->userAgent(),
            ]
        );

        return redirect()->route('user.index')->with(['message' => 'User Created Successfully', 'msg_type' => 'success']);
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        //
    }

    public function permissions(User $user)
    {
        $allPermissions = Permission::orderBy('name')->get();
        $grantedPermissionIds = $user->getAllPermissions()->pluck('id')->toArray();

        // Define human-readable names for modules
        $moduleNames = [
            'consumableInternalName' => 'Consumable Internal Names',
            'consumableInternalNameGroup' => 'Internal Name Groups',
            'consumableInternalNameReport' => 'Internal Name Reports',
            'dashboard' => 'Dashboard',
            'expense' => 'Expenses',
            'client' => 'Clients',
            'supplier' => 'Suppliers',
            'user' => 'Users',
            'role' => 'Roles',
            'permission' => 'Permissions',
            'activityLog' => 'Activity Logs',
            'setting' => 'Settings',
            'signinLog' => 'Signin Logs',
            'profile' => 'Profiles',
            'munit' => 'Measurement Units',
            'pgroup' => 'Product Groups',
            'location' => 'Locations',
            'expuser' => 'Expense Users',
            'expcate' => 'Expense Categories',
            'product' => 'Products',
            'purchase' => 'Purchases',
            'opening' => 'Opening Entries',
            'openStock' => 'Open Stock',
            'outward' => 'Outward Issues',
            'stocks' => 'Stock Status',
            'signageCostSheet' => 'Signage Cost Sheets',
            'cabinetCostSheet' => 'Cabinet Cost Sheets',
            'lettersCostSheet' => 'Letters Cost Sheets',
            'salesOrder' => 'Sales Orders',
            'enquiry' => 'Enquiries',
            'workflow' => 'Workflows',
            'job' => 'Jobs',
            'task' => 'Tasks',
        ];

        $grouped = [];
        $moduleKeys = array_keys($moduleNames);

        foreach ($moduleKeys as $mKey) {
            $grouped[$mKey] = [
                'name' => $moduleNames[$mKey],
                'child' => [],
                'sts' => false,
            ];
        }

        foreach ($allPermissions as $perm) {
            $name = $perm->name;
            $matchedModule = null;
            $action = null;

            foreach ($moduleKeys as $mKey) {
                $prefix = $mKey.'_';
                if (str_starts_with($name, $prefix)) {
                    $matchedModule = $mKey;
                    $action = substr($name, strlen($prefix));
                    break;
                }
            }

            if (! $matchedModule || $action === '' || $action === false) {
                continue;
            }

            $isChecked = in_array($perm->id, $grantedPermissionIds, true);
            $grouped[$matchedModule]['child'][] = [$perm->id, $action, $isChecked];
            $grouped[$matchedModule]['sts'] = $grouped[$matchedModule]['sts'] || $isChecked;
        }

        // Remove modules with no actions, but keep keys
        $grouped = array_filter($grouped, function ($group) {
            return ! empty($group['child']);
        });

        return Inertia::render('Admin/UserPermissionsEditView', [
            'user' => $user,
            'allpermissions' => $grouped,
        ]);
    }

    public function permissionsUpdate(Request $request)
    {
        $user = User::find($request->userid);
        $isDirty = false;

        $allgivenpermission = [];
        $user->permissions()->detach();
        foreach ($user->getAllPermissions() as $uperm) {
            $allgivenpermission[] = $uperm->id;
        }
        $allnewpermission = [];
        foreach ($request->permission as $grouppermssion) {
            foreach ($grouppermssion['child'] as $permdata) {
                if ($permdata[2]) {
                    $allnewpermission[] = $permdata[0];
                }
            }
        }
        $diff1 = array_diff($allgivenpermission, $allnewpermission);
        $diff2 = array_diff($allnewpermission, $allgivenpermission);
        if (! empty($diff1) || ! empty($diff2)) {
            $isDirty = true;
        }

        $user->givePermissionTo($allnewpermission);
        if ($isDirty) {
            $uname = $user->name.'-'.$user->role_name;
            \ActivityLog::add(['action' => 'updated', 'module' => 'UserPermission', 'data_key' => $uname]);
            $res = ['message' => 'UserPermission Updated Successfully.', 'msg_type' => 'success'];
        } else {
            $res = ['message' => 'No Value  Updated in UserPermission .', 'msg_type' => 'warning'];
        }

        return redirect()->route('user.permissions', $request->userid)->with($res);
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        $formdata = User::where('id', $id)->with('roles')->get()->first();
        $formdata->append(['role_name', 'is_super_admin']);
        if ($formdata->is_super_admin) {
            return redirect()->route('user.index')->with(['message' => 'You don\'t have permission to update Super Admin', 'msg_type' => 'danger']);
        }

        $formdata->roles_data = $formdata->roles->map(function ($role) {
            return [
                'id' => $role->id,
                'label' => $role->name,
            ];
        });

        $roles = Role::select('id', 'name as label')->where('name', '<>', 'super-admin')->get();

        return Inertia::render('Admin/UserAddEditView', compact('roles', 'formdata'));
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, User $user)
    {
        $user->append(['role_name', 'is_super_admin']);
        if ($user->is_super_admin) {
            return redirect()->route('user.index')->with(['message' => 'You don\'t have permission to update Super Admin', 'msg_type' => 'danger']);
        }
        $isDirty = false;
        $validationRules = [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|unique:users,email,'.$user->id,
            'phone' => 'nullable|string|max:20',
            'roles' => 'required|array|min:1',
            'roles.*.id' => 'required|exists:roles,id',
        ];

        $request->validate($validationRules);

        $roleIds = collect($request->roles)->pluck('id')->all();

        // Enforce exclusivity of super-admin role
        $hasSuperAdmin = Role::whereIn('id', $roleIds)->where('name', 'super-admin')->exists();
        if ($hasSuperAdmin) {
            return redirect()->back()->withErrors(['roles' => 'The super-admin role cannot be assigned.'])->withInput();
        }

        $user->name = $request->name;
        $user->email = $request->email;
        $user->phone = $request->phone;
        $user->twofa = $request->twofa ? 1 : 0;

        if (! empty($request->password)) {
            $user->password = $request->password;
        }
        if ($user->isDirty()) {
            $user->save();
            $isDirty = true;
        }

        $prevRoleIds = $user->roles->pluck('id')->all();
        sort($roleIds);
        sort($prevRoleIds);

        if ($roleIds !== $prevRoleIds) {
            $user->syncRoles($roleIds);
            $isDirty = true;
            // Reload roles to ensure the dynamic role_name getter reflects the update
            $user->load('roles');
        }

        if ($isDirty) {
            $uname = $user->name.'-'.$user->role_name;
            \ActivityLog::add(['action' => 'updated', 'module' => 'user', 'data_key' => $uname]);
            $res = ['message' => 'User Updated Successfully.', 'msg_type' => 'success'];
        } else {
            $res = ['message' => 'No Value  Updated in user .', 'msg_type' => 'warning'];
        }

        return redirect()->route('user.index')->with($res);
    }

    public function updateProfile(Request $request)
    {
        $user = User::find(Auth::user()->id);

        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|unique:users,email,'.$user->id,
            'phone' => 'nullable|string|max:20',
            'password' => [new NotUsedPassword],
            'current_password' => 'current_password',
        ]);
        $user->name = $request->name;
        $user->email = $request->email;
        $user->phone = $request->phone;
        $user->twofa = $request->twofa;
        if ($request->twofa) {
            Session::put('user_2fa', auth()->user()->id);
        } else {
            Session::remove('user_2fa');
        }

        if (! empty($request->password)) {
            $user->password = $request->password;
        }
        $user->save();
        $uname = $user->name.'-'.$user->role_name;
        \ActivityLog::add(['action' => 'updated', 'module' => 'profile', 'data_key' => $uname]);

        return redirect()->route('profile.profile')->with(['message' => 'Profile Updated Successfully', 'msg_type' => 'success']);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy(User $user)
    {
        $user->append(['role_name', 'is_super_admin']);
        if ($user->is_super_admin) {
            return redirect()->route('user.index')->with(['message' => 'You don\'t have permission to delete Super Admin', 'msg_type' => 'danger']);
        }
        if (auth()->user()->id == $user->id) {
            return redirect()->route('user.index')->with(['message' => 'You Can\'t delete yourself.Request Super admin to do it.', 'msg_type' => 'danger']);
        }

        $uname = $user->name.'-'.$user->role_name;
        $user->delete();
        \ActivityLog::add(['action' => 'deleted', 'module' => 'user', 'data_key' => $uname]);

        return redirect()->route('user.index')->with(['message' => 'User Deleted !!']);
    }

    public function authDestroy()
    {
        if ((Hash::check(request('password'), Auth::user()->password))) {
            $user = User::find(request('id'));

            return $this->destroy($user);
        } else {
            return redirect()->route('user.index')->with(['message' => 'Authentication Failed!!', 'msg_type' => 'danger']);
        }
    }

    public function getExecutiveOptions()
    {
        return response()->json(
            User::role('executive')
                ->select('id', 'name as label', 'email')
                ->orderBy('name')
                ->get()
        );
    }

    public function storeExecutive(Request $request)
    {
        if (! Auth::user()->can('user_create')) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|unique:users,email',
            'phone' => 'nullable|string|max:20',
            'password' => 'required|string|min:8',
        ]);

        \DB::beginTransaction();
        try {
            $user = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'phone' => $request->phone,
                'password' => $request->password,
                'twofa' => false,
            ]);

            $executiveRole = Role::firstOrCreate(['name' => 'executive']);
            $user->assignRole($executiveRole);

            \DB::commit();

            $uname = $user->name.'-executive';
            \ActivityLog::add(['action' => 'created', 'module' => 'user', 'data_key' => $uname]);

            try {
                User::neUserMail([
                    'password' => $request->password,
                    'ip' => $request->ip(),
                    'user' => $user,
                    'userAgent' => $request->userAgent(),
                ]);
            } catch (\Exception $mailEx) {
                // Ignore mail sending errors during dynamic creation
                info('Dynamic user creation mail failed: '.$mailEx->getMessage());
            }

            $executives = User::role('executive')
                ->select('id', 'name as label', 'email')
                ->orderBy('name')
                ->get();

            return response()->json([
                'message' => 'Executive created successfully',
                'user' => [
                    'id' => $user->id,
                    'label' => $user->name,
                    'email' => $user->email,
                ],
                'executives' => $executives,
            ]);
        } catch (\Exception $e) {
            \DB::rollBack();

            return response()->json(['message' => 'Failed to create user: '.$e->getMessage()], 500);
        }
    }
}
