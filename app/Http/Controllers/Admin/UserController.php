<?php

namespace App\Http\Controllers\Admin;

use App\Models\Role;
use App\Models\User;

use Inertia\Inertia;
use Illuminate\Http\Request;
use App\Rules\NotUsedPassword;
use Illuminate\Support\Collection;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Spatie\QueryBuilder\QueryBuilder;
use Spatie\QueryBuilder\AllowedFilter;
use Illuminate\Support\Facades\Session;
use Spatie\Permission\Models\Permission;
use ProtoneMedia\LaravelQueryBuilderInertiaJs\InertiaTable;

class UserController extends Controller
{
    protected $resourceNeo = ['resourceName' => 'user', 'resourceTitle' => 'Users', 'iconPath' => 'M4,6H2V20A2,2 0 0,0 4,22H18V20H4V6M20,2A2,2 0 0,1 22,4V16A2,2 0 0,1 20,18H8A2,2 0 0,1 6,16V4A2,2 0 0,1 8,2H20M17,7A3,3 0 0,0 14,4A3,3 0 0,0 11,7A3,3 0 0,0 14,10A3,3 0 0,0 17,7M8,15V16H20V15C20,13 16,11.9 14,11.9C12,11.9 8,13 8,15Z'];

    public function __construct()
    {
        $this->middleware('can:user_list', ['only' => ['index', 'show']]);
        $this->middleware('can:user_create', ['only' => ['create', 'store']]);
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

        // Append role_name to each user for frontend display
        $users->getCollection()->transform(function ($user) {
            $user->append('role_name');
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
        $roles = Role::select('id', 'name as label')->where('id', '<>', 3)->get();

        return Inertia::render('Admin/UserAddEditView', compact('roles'));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $validationRules = [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|unique:users,email',
            'password' => 'required|string|min:8',
            'role' => 'required',
        ];



        $request->validate($validationRules);

        $role = Role::findById($request->role['id']);


        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => $request->password,
            'twofa' => $request->twofa,

        ]);
        $user->assignRole($role);

        $uname = $request->input('name') . '-' . $role->name;
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

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function permissions(User $user)
    {
        $grantedPermissionIds = $user->getAllPermissions()->pluck('id')->all();

        $allPermissions = Permission::orderBy('name')->get();
        $modules = config('app.modules', []); // ['module_key' => 'Readable Name']

        // Keep module keys order as defined in config
        $moduleKeys = array_keys($modules);

        // Build buckets in config order
        $grouped = [];
        foreach ($moduleKeys as $mKey) {
            $grouped[$mKey] = ['sts' => false, 'child' => [], 'name' => $modules[$mKey] ?? ucfirst(str_replace('-', ' ', $mKey))];
        }

        foreach ($allPermissions as $perm) {
            $name = $perm->name;
            if (str_starts_with($name, 'permission')) {
                continue; // skip generic permission entries
            }

            $matchedModule = null;
            $action = null;

            foreach ($moduleKeys as $mKey) {
                $prefix = $mKey . '_';
                if (str_starts_with($name, $prefix)) {
                    $matchedModule = $mKey;
                    $action = substr($name, strlen($prefix));
                    break;
                }
            }

            if (!$matchedModule || $action === '' || $action === false) {
                continue;
            }

            $isChecked = in_array($perm->id, $grantedPermissionIds, true);
            $grouped[$matchedModule]['child'][] = [$perm->id, $action, $isChecked];
            $grouped[$matchedModule]['sts'] = $grouped[$matchedModule]['sts'] || $isChecked;
        }

        // Remove modules with no actions
        $grouped = array_values(array_filter($grouped, function ($group) {
            return !empty($group['child']);
        }));

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
                    $permission = Permission::findById($permdata[0]);
                    $allnewpermission[] = $permdata[0];
                    if (!$user->hasPermissionTo($permission)) {
                        $user->givePermissionTo($permission);
                    }
                }
            }
        }
        sort($allnewpermission);
        sort($allgivenpermission);

        if ($allnewpermission != $allgivenpermission) {
            $isDirty = true;
        }
        if ($isDirty) {
            $uname = $user->name;
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
        $formdata->append('role_name');
        if ($formdata->role_name == 'super-admin') {
            return redirect()->route('user.index')->with(['message' => 'You don\'t have permission to update Super Admin', 'msg_type' => 'danger']);
        }
        $formdata->role = count($formdata->roles) > 0 ? Role::select('id', 'name as label')->where('id', $formdata->roles[0]->id)->get() : 0;
        $roles = array_merge([['id' => 0, 'label' => 'Select']], Role::select('id', 'name as label')->where('id', '<>', 3)->get()->toArray());



        //dd($roles);
        return Inertia::render('Admin/UserAddEditView', compact('roles', 'formdata'));
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */

    public function update(Request $request, user $user)
    {
        // dd($request);
        if ($user->role_name == 'super-admin') {
            return redirect()->route('user.index')->with(['message' => 'You don\'t have permission to update Super Admin', 'msg_type' => 'danger']);
        }
        $isDirty = false;
        $validationRules = [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|unique:users,email,' . $user->id,
            'role' => 'required',
        ];



        $request->validate($validationRules);

        $user->name = $request->name;
        $user->email = $request->email;
        $user->twofa = $request->twofa ? 1 : 0;

        if (!empty($request->password)) {
            $user->password = $request->password;
        }
        if ($user->isDirty()) {
            $user->save();
            $isDirty = true;
        }



        if ($request->role['id'] > 0) {
            $prevrole = $user->roles[0]->id;
            $role = Role::findById($request->role['id']);
            $user->syncRoles($role);
            if ($prevrole != $request->role['id']) {
                $isDirty = true;
            }
        } else {
            $user->roles()->detach();
        }
        if ($isDirty) {
            $uname = $user->name . '-' . $user->role_name;
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
            'email' => 'required|string|email|unique:users,email,' . $user->id,
            'password' => [new NotUsedPassword()],
            'current_password' => 'current_password',
        ]);
        $user->name = $request->name;
        $user->email = $request->email;
        $user->twofa = $request->twofa;
        if ($request->twofa) {
            Session::put('user_2fa', auth()->user()->id);
        } else {
            Session::remove('user_2fa');
        }

        if (!empty($request->password)) {
            $user->password = $request->password;
        }
        $user->save();
        $uname = $user->name . '-' . $user->role_name;
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
        if ($user->role_name == 'super-admin') {
            return redirect()->route('user.index')->with(['message' => 'You don\'t have permission to delete Super Admin', 'msg_type' => 'danger']);
        }
        if (auth()->user()->id == $user->id) {
            return redirect()->route('user.index')->with(['message' => 'You Can\'t delete yourself.Request Super admin to do it.', 'msg_type' => 'danger']);
        }

        $uname = $user->name . '-' . $user->role_name;
        $user->delete();
        \ActivityLog::add(['action' => 'deleted', 'module' => 'user', 'data_key' => $uname]);
        return redirect()->route('user.index')->with(['message' => 'User Deleted !!']);
    }

    public function authDestroy()
    {
        if ((Hash::check(request('password'), Auth::user()->password))) {
            $user = User::find(request('id'));
            $this->destroy($user);
        } else {
            return redirect()->route('user.index')->with(['message' => 'Athentication Failed!!', 'msg_type' => 'danger']);
        }
    }
}
