<?php

namespace App\Helpers;

use App\Models\LogActivity;
use Illuminate\Support\Facades\Auth;

class ActivityLog
{
    public static function add($data)
    {
        $log = [];
        $log['ip'] = request()->ip();
        $log['user_agent'] = request()->userAgent();
        $log['user_id'] = Auth::user()->id;
        LogActivity::create(array_merge($log, $data));
    }
}
