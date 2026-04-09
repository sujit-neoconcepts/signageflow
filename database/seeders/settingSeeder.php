<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class settingSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('settings')->delete();

        Setting::create(['slug' => 'lock_user_attempt','label' => 'Number of times user failed to login to get locked','value' => '4','vtype' => 'number','group' => 'General','access_roles' => 'super-admin']);

        Setting::create(['slug' => 'lock_user_duration','label' => 'Number of seconds user locked for','value' => '300','vtype' => 'number','group' => 'General','access_roles' => 'super-admin']);

        Setting::create(['slug' => 'otp_reset_duration','label' => 'Re-Send OTP after seconds','value' => '180','vtype' => 'number','group' => 'General','access_roles' => 'super-admin']);
    }
}
