<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class UnifiedPermissionSeeder extends Seeder
{
    /**
     * Unified permission mapping following the convention: moduleName_permissionName
     * - Module names: camelCase, no underscores
     * - Permission names: camelCase, no underscores
     * - Single underscore separator between module and permission
     */
    protected array $permissions = [
        // User Management
        'permission' => ['list', 'create', 'edit', 'delete'],
        'role' => ['list', 'create', 'edit', 'delete', 'export'],
        'user' => ['list', 'create', 'edit', 'delete', 'export'],
        'signinLog' => ['list', 'delete', 'export', 'view'],
        'activityLog' => ['list', 'delete', 'export'],

        // Master Data
        'supplier' => ['list', 'create', 'edit', 'delete', 'export', 'import'],
        'client' => ['list', 'create', 'edit', 'delete', 'export'],
        'setting' => ['list', 'create', 'edit', 'delete'],

        // Consumables Master Modules
        'munit' => ['list', 'create', 'edit', 'delete', 'export', 'import'],
        'pgroup' => ['list', 'create', 'edit', 'delete', 'export', 'import'],
        'location' => ['list', 'create', 'edit', 'delete', 'export', 'import'],
        'expuser' => ['list', 'create', 'edit', 'delete', 'export', 'import'],
        'expcate' => ['list', 'create', 'edit', 'delete', 'export'],
        'consumableInternalName' => ['list', 'create', 'edit', 'delete', 'export', 'import'],
        'consumableInternalNameGroup' => ['list', 'create', 'edit', 'delete', 'export', 'import'],
        'consumableInternalNameReport' => ['list'],
        'consumableInternalNameGroupReport' => ['list'],

        // Consumables Operational Modules
        'product' => ['list', 'create', 'edit', 'delete', 'export', 'import'],
        'purchase' => ['list', 'list_for_all', 'create', 'edit', 'delete', 'export'],
        'outward' => ['list', 'list_for_all', 'create', 'edit', 'delete', 'export', 'add_for_all'],
        'opening' => ['list', 'list_for_all', 'create', 'edit', 'delete', 'export', 'import'],
        'stocks' => ['list', 'list_for_all', 'export', 'import'],
        'expense' => ['list', 'list_for_all', 'create', 'edit', 'delete', 'export', 'add_for_all'],
        'openStock' => ['list', 'create', 'edit', 'delete', 'export', 'adjust'],
        'signageCostSheet' => ['list', 'create', 'edit', 'delete', 'export'],
        'cabinetCostSheet' => ['list', 'create', 'edit', 'delete', 'export'],
        'lettersCostSheet' => ['list', 'create', 'edit', 'delete', 'export'],
        'salesOrder' => ['list', 'create', 'edit', 'delete', 'export'],
        'enquiry' => ['list', 'create', 'edit', 'delete', 'export'],
        'workflow' => ['list', 'create', 'edit', 'delete', 'view'],
        'job' => ['list', 'create', 'edit', 'delete', 'view'],
        'dashboard' => ['view', 'viewStockMetrics', 'viewPurchaseMetrics', 'viewExpenseMetrics', 'viewOutwardMetrics', 'viewJobMetrics', 'viewTaskMetrics', 'viewMyTasks'],
    ];

    public function run(): void
    {
        // Reset cached roles and permissions
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // Generate all permissions following the convention
        $allPermissions = [];
        foreach ($this->permissions as $module => $actions) {
            foreach ($actions as $action) {
                $allPermissions[] = $module.'_'.$action;
            }
        }

        // Create permissions
        foreach ($allPermissions as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }

        // Create/Retrieve Roles
        $adminRole = Role::firstOrCreate(['name' => 'admin']);
        $supervisorRole = Role::firstOrCreate(['name' => 'supervisor']);
        $executiveRole = Role::firstOrCreate(['name' => 'executive']);
        $inchargeRole = Role::firstOrCreate(['name' => 'incharge']);
        $managerRole = Role::firstOrCreate(['name' => 'manager']);

        // Give permissions to Admin
        $adminRole->givePermissionTo([
            'dashboard_view',
            'dashboard_viewStockMetrics',
            'dashboard_viewPurchaseMetrics',
            'dashboard_viewExpenseMetrics',
            'dashboard_viewOutwardMetrics',
            'dashboard_viewJobMetrics',
            'dashboard_viewTaskMetrics',
            'dashboard_viewMyTasks',
        ]);

        // Give permissions to Supervisor
        $supervisorRole->givePermissionTo([
            'dashboard_view',
            'dashboard_viewStockMetrics',
            'dashboard_viewJobMetrics',
            'dashboard_viewTaskMetrics',
            'dashboard_viewMyTasks',
        ]);

        // Give permissions to Executive
        $executiveRole->givePermissionTo([
            'dashboard_view',
            'dashboard_viewMyTasks',
        ]);

        // Give permissions to Incharge
        $inchargeRole->givePermissionTo([
            'dashboard_view',
            'dashboard_viewMyTasks',
        ]);

        // Give permissions to Manager
        $managerRole->givePermissionTo([
            'dashboard_view',
            'dashboard_viewMyTasks',
        ]);
    }
}
