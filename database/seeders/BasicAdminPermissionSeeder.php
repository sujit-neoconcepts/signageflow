<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

use Illuminate\Support\Facades\Hash;

class BasicAdminPermissionSeeder extends Seeder
{
    /**
     * Create the initial roles and permissions.
     *
     * @return void
     */
    public function run()
    {
        // Reset cached roles and permissions
        app()[PermissionRegistrar::class]->forgetCachedPermissions();
        // create permissions
        $permissions = [
            'permission_list',
            'permission_create',
            'permission_edit',
            'permission_delete',
            'role_list',
            'role_create',
            'role_edit',
            'role_delete',
            'role_export',
            'user_list',
            'user_create',
            'user_edit',
            'user_delete',
            'user_export',
            'signinlog_list',
            'signinlog_delete',
            'signinlog_export',
            'activitylog_list',
            'activitylog_delete',
            'activitylog_export',

        ];
        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }
        // create roles and assign existing permissions
        $role1 = Role::firstOrCreate(['name' => 'supervisor']);
        //$role1->givePermissionTo('page_list');
        $role2 = Role::firstOrCreate(['name' => 'admin']);
        foreach ($permissions as $permission) {
            $role2->givePermissionTo($permission);
        }
        $role3 = Role::firstOrCreate(['name' => 'super-admin']);
        // gets all permissions via Gate::before rule; see AuthServiceProvider
        // create demo users
        $user = \App\Models\User::firstOrCreate(
            ['email' => 'superadmin@superadmin.com'],
            [
                'name' => 'Super Admin',
                'password' =>'superadmin',
            ]
        );
        $user->assignRole($role3);
        
        $user = \App\Models\User::firstOrCreate(
            ['email' => 'admin@admin.com'],
            [
                'name' => 'Admin User',
                'password' =>'admin',
            ]
        );
        $user->assignRole($role2);
        
        $user = \App\Models\User::firstOrCreate(
            ['email' => 'supervisor@supervisor.com'],
            [
                'name' => 'supervisor User',
                'password' =>'supervisor',
            ]
        );
        $user->assignRole($role1);

        $role4 = Role::firstOrCreate(['name' => 'incharge']);
        $incharge = \App\Models\User::firstOrCreate(
            ['email' => 'incharge@incharge.com'],
            [
                'name' => 'Incharge',
                'password' =>'incharge',
            ]
        );
        $incharge->assignRole($role4);
    }
}
