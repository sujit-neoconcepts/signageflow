<?php

namespace SignageFlow\Hrms\Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class HrmsPermissionSeeder extends Seeder
{
    protected array $permissions = [
        'hrmsDashboard' => ['list', 'view'],
        'hrmsOrg' => ['list', 'create', 'edit', 'delete'],
        'hrmsEmployee' => ['list', 'create', 'edit', 'delete', 'import', 'export', 'bulkUpdate'],
        'hrmsAdmin' => ['list', 'edit', 'viewAudit', 'manageNotifications'],
        'hrmsAttendance' => ['list', 'create', 'edit', 'delete', 'approve', 'export', 'import', 'self'],
        'hrmsAttendanceSetup' => ['list', 'create', 'edit', 'delete'],
        'hrmsAttendanceReport' => ['list', 'export'],
        'hrmsLeave' => ['list', 'create', 'edit', 'delete', 'approve', 'applyOnBehalf', 'adjust', 'yearEnd', 'export', 'import', 'self'],
        'hrmsLeaveSetup' => ['list', 'create', 'edit', 'delete'],
        'hrmsLeaveReport' => ['list', 'export'],
        // Phase 2 & 3 permission keys
        'hrmsPayrollSetup' => ['list', 'create', 'edit', 'delete'],
        'hrmsPayroll' => ['list', 'create', 'edit', 'delete', 'approveL1', 'approveL2', 'publish', 'export', 'selfPayslip'],
        'hrmsLoan' => ['list', 'create', 'edit', 'delete'],
        'hrmsClaim' => ['list', 'create', 'approve', 'export'],
        'hrmsPayrollReport' => ['list', 'export'],
        'hrmsTax' => ['list', 'verify', 'export', 'self'],
        'hrmsTimesheet' => ['list', 'create', 'approve', 'export'],
    ];

    public function run(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $all = [];
        foreach ($this->permissions as $module => $actions) {
            foreach ($actions as $action) {
                $name = $module.'_'.$action;
                $all[] = $name;
                Permission::firstOrCreate(['name' => $name]);
            }
        }

        if ($admin = Role::where('name', 'admin')->first()) {
            $admin->givePermissionTo($all);
        }

        if ($manager = Role::where('name', 'manager')->first()) {
            $manager->givePermissionTo([
                'hrmsDashboard_list', 'hrmsDashboard_view',
                'hrmsEmployee_list',
                'hrmsAttendance_list', 'hrmsAttendance_approve', 'hrmsAttendance_export', 'hrmsAttendance_self',
                'hrmsAttendanceReport_list', 'hrmsAttendanceReport_export',
                'hrmsLeave_list', 'hrmsLeave_approve', 'hrmsLeave_applyOnBehalf', 'hrmsLeave_export', 'hrmsLeave_self',
                'hrmsLeaveReport_list', 'hrmsLeaveReport_export',
                'hrmsPayroll_selfPayslip',
            ]);
        }

        if ($executive = Role::where('name', 'executive')->first()) {
            $executive->givePermissionTo([
                'hrmsAttendance_self',
                'hrmsLeave_self',
                'hrmsPayroll_selfPayslip',
                'hrmsTax_self',
            ]);
        }
    }
}
