<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
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

        // Consumables Operational Modules
        'product' => ['list', 'create', 'edit', 'delete', 'export', 'import'],
        'purchase' => ['list','list_for_all', 'create', 'edit', 'delete', 'export'],
        'outward' => ['list', 'list_for_all', 'create', 'edit', 'delete', 'export','add_for_all'],
        'opening' => ['list','list_for_all', 'create', 'edit', 'delete', 'export', 'import'],
        'stocks' => ['list', 'list_for_all', 'export'],
        'expense' => ['list', 'list_for_all', 'create', 'edit', 'delete', 'export','add_for_all'],
        'openStock' => ['list', 'create', 'edit', 'delete', 'export','adjust'],
        'signageCostSheet' => ['list', 'create', 'edit', 'delete', 'export'],
        'cabinetCostSheet' => ['list', 'create', 'edit', 'delete', 'export'],
        'lettersCostSheet' => ['list', 'create', 'edit', 'delete', 'export'],
        'salesOrder' => ['list', 'create', 'edit', 'delete', 'export'],
        'enquiry' => ['list', 'create', 'edit', 'delete', 'export'],
    ];

    public function run(): void
    {
        // Reset cached roles and permissions
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // Generate all permissions following the convention
        $allPermissions = [];
        foreach ($this->permissions as $module => $actions) {
            foreach ($actions as $action) {
                $allPermissions[] = $module . '_' . $action;
            }
        }

        // Create permissions
        foreach ($allPermissions as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }
    }
}
