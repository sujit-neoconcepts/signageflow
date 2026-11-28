<?php

namespace SignageFlow\Hrms\Tests;

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Schema;

abstract class HrmsTestCase extends BaseTestCase
{
    public function createApplication()
    {
        $app = require __DIR__.'/../../../../bootstrap/app.php';
        $app->make(Kernel::class)->bootstrap();

        return $app;
    }

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
        ]);

        // Minimal host tables required by HRMS foreign keys
        if (! Schema::hasTable('users')) {
            Schema::create('users', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('email')->unique();
                $table->string('password')->default('');
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('locations')) {
            Schema::create('locations', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('expenses')) {
            Schema::create('expenses', function (Blueprint $table) {
                $table->id();
                $table->date('exp_date')->nullable();
                $table->decimal('amount', 12, 2)->default(0);
                $table->string('doneby')->nullable();
                $table->string('exp_cate')->nullable();
                $table->text('details')->nullable();
                $table->string('incharge')->nullable();
                $table->string('amt_type')->default('Expense');
                $table->timestamps();
            });
        }

        $this->artisan('migrate', [
            '--path' => 'packages/signageflow/hrms/database/migrations',
            '--force' => true,
        ]);
    }
}
