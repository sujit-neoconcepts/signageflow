<?php

namespace SignageFlow\Hrms\Models;

use Illuminate\Database\Eloquent\Model;

class NotificationLog extends Model
{
    protected $table = 'hrms_notification_logs';

    protected $guarded = ['id'];

    protected $casts = ['sent_at' => 'datetime'];
}
