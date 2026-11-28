<?php

return [
    'table_prefix' => 'hrms_',

    'route_prefix' => 'admin/hrms',
    'kiosk_prefix' => 'hrms/kiosk',
    'mobile_api_prefix' => 'api/mobile/hrms',

    'financial_year_start_month' => 4, // April 1 - March 31

    'storage_disk' => env('HRMS_STORAGE_DISK', 'local'),
    'storage_path' => 'hrms',

    'models' => [
        'user' => App\Models\User::class,
        'location' => App\Models\Location::class,
        'job' => App\Models\Job::class,
        'task' => App\Models\Task::class,
        'expense' => App\Models\Expense::class,
    ],

    'attendance' => [
        'default_geofence_radius_meters' => 150,
        'default_grace_in_minutes' => 15,
        'default_grace_out_minutes' => 10,
        'default_half_day_minutes' => 240,
        'default_full_day_minutes' => 480,
        'default_ot_min_threshold_minutes' => 30,
        'default_ot_multiplier' => 1.5,
    ],

    'leave' => [
        'default_pagination' => 25,
        'pagination_options' => [5, 10, 25, 50, 100],
    ],
];
