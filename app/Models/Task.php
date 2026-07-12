<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class Task extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'description',
        'creator_id',
        'due_date',
        'start_date',
        'end_date',
        'estimated_hours',
        'start_on_previous_complete',
        'status',
        'priority',
        'is_recurring',
        'recurrence_type',
        'recurrence_config',
        'recurrence_end_date',
        'parent_task_id',
        'job_id',
        'job_stage_sort_order',
        'last_recurrence_generated_at',
        'notify_channels',
        'reminder_before_due',
        'reminder_sent',
        'start_notified',
        'enquiry_no',
        'sales_order_no',
        'need_enquiry_number',
        'need_sales_order_number',
    ];

    protected $casts = [
        'due_date' => 'datetime',
        'start_date' => 'datetime',
        'end_date' => 'datetime',
        'estimated_hours' => 'decimal:2',
        'start_on_previous_complete' => 'boolean',
        'is_recurring' => 'boolean',
        'recurrence_config' => 'array',
        'recurrence_end_date' => 'date',
        'last_recurrence_generated_at' => 'datetime',
        'notify_channels' => 'array',
        'reminder_sent' => 'boolean',
        'start_notified' => 'boolean',
        'need_enquiry_number' => 'boolean',
        'need_sales_order_number' => 'boolean',
    ];

    public function creator()
    {
        return $this->belongsTo(User::class, 'creator_id');
    }

    public function assignees()
    {
        return $this->belongsToMany(User::class, 'task_assignees', 'task_id', 'user_id')
            ->withPivot('status', 'feedback', 'completed_at')
            ->withTimestamps();
    }

    public function viewers()
    {
        return $this->belongsToMany(User::class, 'task_viewers', 'task_id', 'user_id')
            ->withTimestamps();
    }

    public function files()
    {
        return $this->hasMany(TaskFile::class);
    }

    public function comments()
    {
        return $this->hasMany(TaskComment::class);
    }

    public function expenses()
    {
        return $this->hasMany(Expense::class, 'task_id');
    }

    public function parentTask()
    {
        return $this->belongsTo(Task::class, 'parent_task_id');
    }

    public function childTasks()
    {
        return $this->hasMany(Task::class, 'parent_task_id');
    }

    public function job()
    {
        return $this->belongsTo(Job::class, 'job_id');
    }

    public function scopeDueDateStart($query, $sd)
    {
        $start = ($sd instanceof Carbon) ? $sd : Carbon::parse($sd);

        return $query->where('due_date', '>=', $start->startOfDay());
    }

    public function scopeDueDateEnd($query, $ed)
    {
        $end = ($ed instanceof Carbon) ? $ed : Carbon::parse($ed);

        return $query->where('due_date', '<=', $end->endOfDay());
    }
}
