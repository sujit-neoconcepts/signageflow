<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Job extends Model
{
    use HasFactory;

    protected $table = 'jobs_manager';

    protected $fillable = [
        'title',
        'description',
        'client_id',
        'workflow_id',
        'due_date',
        'estimated_hours',
        'status',
        'created_by',
    ];

    protected $casts = [
        'due_date' => 'datetime',
        'estimated_hours' => 'decimal:2',
    ];

    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    public function workflow()
    {
        return $this->belongsTo(Workflow::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function tasks()
    {
        return $this->hasMany(Task::class, 'job_id')->orderBy('job_stage_sort_order');
    }

    public function files()
    {
        return $this->hasMany(JobFile::class, 'job_id');
    }

    public function expenses()
    {
        return $this->hasMany(Expense::class, 'job_id');
    }

    /**
     * Recalculate job status based on linked task statuses.
     */
    public function recalculateStatus()
    {
        $tasks = $this->tasks()->get();

        if ($tasks->isEmpty()) {
            $this->update(['status' => 'not_started']);

            return;
        }

        $statuses = $tasks->pluck('status')->all();

        // If all tasks are closed → job is closed
        if (count(array_unique($statuses)) === 1 && $statuses[0] === 'closed') {
            $this->update(['status' => 'closed']);

            return;
        }

        // If all tasks are completed or verified or closed → job is completed
        $completedStatuses = ['completed', 'verified', 'closed'];
        $allCompleted = collect($statuses)->every(fn ($s) => in_array($s, $completedStatuses));
        if ($allCompleted) {
            $this->update(['status' => 'completed']);

            return;
        }

        // If any task is not pending → in_progress
        $activeStatuses = ['accepted', 'in_progress', 'completed', 'verified', 'closed'];
        $anyActive = collect($statuses)->contains(fn ($s) => in_array($s, $activeStatuses));
        if ($anyActive) {
            $this->update(['status' => 'in_progress']);

            return;
        }

        $this->update(['status' => 'not_started']);
    }
}
