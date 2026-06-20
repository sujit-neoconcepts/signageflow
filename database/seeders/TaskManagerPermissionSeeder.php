<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class TaskManagerPermissionSeeder extends Seeder
{
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // Task permissions
        $permissions = [
            'task_list',
            'task_create',
            'task_edit',
            'task_delete',
            'task_view',
            'task_MyTasksList',
        ];

        // Workflow permissions
        $workflowPermissions = [
            'workflow_list',
            'workflow_create',
            'workflow_edit',
            'workflow_delete',
            'workflow_view',
        ];

        // Job permissions
        $jobPermissions = [
            'job_list',
            'job_create',
            'job_edit',
            'job_delete',
            'job_view',
        ];

        $allNewPermissions = array_merge($permissions, $workflowPermissions, $jobPermissions);

        foreach ($allNewPermissions as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }

        // Roles
        $managerRole = Role::firstOrCreate(['name' => 'manager']);
        $executiveRole = Role::firstOrCreate(['name' => 'executive']);
        $adminRole = Role::firstOrCreate(['name' => 'admin']);
        $superAdminRole = Role::firstOrCreate(['name' => 'super-admin']);

        // Give permissions to manager
        $managerRole->givePermissionTo($allNewPermissions);

        // Give permissions to executive
        $executiveRole->givePermissionTo([
            'task_MyTasksList',
            'workflow_view',
            'job_view',
        ]);

        // Give permissions to admin
        foreach ($allNewPermissions as $permission) {
            $adminRole->givePermissionTo($permission);
        }

        // Create default manager and executive users if they don't exist
        $managerUser = User::firstOrCreate(
            ['email' => 'manager@manager.com'],
            [
                'name' => 'Manager User',
                'phone' => '1234567890',
                'password' => 'manager',
            ]
        );
        $managerUser->assignRole($managerRole);

        $executiveUser = User::firstOrCreate(
            ['email' => 'executive@executive.com'],
            [
                'name' => 'Executive User 1',
                'phone' => '1234567891',
                'password' => 'executive',
            ]
        );
        $executiveUser->assignRole($executiveRole);

        $executiveUser2 = User::firstOrCreate(
            ['email' => 'executive2@executive.com'],
            [
                'name' => 'Executive User 2',
                'phone' => '1234567892',
                'password' => 'executive',
            ]
        );
        $executiveUser2->assignRole($executiveRole);
    }
}
