<?php

namespace SignageFlow\Hrms\Tests\Unit;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use SignageFlow\Hrms\Database\Seeders\HrmsSampleDataSeeder;
use SignageFlow\Hrms\Models\Employee;
use SignageFlow\Hrms\Models\LeaveRequest;
use SignageFlow\Hrms\Tests\HrmsTestCase;

class SampleDataSeederTest extends HrmsTestCase
{
    public function test_sample_data_seeder_runs_idempotently(): void
    {
        if (! Schema::hasTable('permissions')) {
            Schema::create('permissions', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('guard_name')->default('web');
                $table->timestamps();
            });
        }
        if (! Schema::hasTable('roles')) {
            Schema::create('roles', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('guard_name')->default('web');
                $table->timestamps();
            });
        }
        if (! Schema::hasTable('role_has_permissions')) {
            Schema::create('role_has_permissions', function (Blueprint $table) {
                $table->unsignedBigInteger('permission_id');
                $table->unsignedBigInteger('role_id');
            });
        }

        $this->seed(HrmsSampleDataSeeder::class);
        $this->seed(HrmsSampleDataSeeder::class);

        $this->assertGreaterThanOrEqual(10, Employee::count());
        $this->assertGreaterThanOrEqual(2, LeaveRequest::count());
    }
}
