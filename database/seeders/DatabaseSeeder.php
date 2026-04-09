<?php

namespace Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * @return void
     */
    public function run()
    {
        // Use the unified permission seeder
        $this->call(BasicAdminPermissionSeeder::class);
        $this->call(UnifiedPermissionSeeder::class);
        //$this->call(settingSeeder::class);
    }
}
