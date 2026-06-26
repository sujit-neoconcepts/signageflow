<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WorkflowStage extends Model
{
    use HasFactory;

    protected $fillable = [
        'workflow_id',
        'name',
        'description',
        'sort_order',
        'default_estimated_hours',
    ];

    protected $casts = [
        'default_estimated_hours' => 'decimal:2',
    ];

    public function workflow()
    {
        return $this->belongsTo(Workflow::class);
    }

    public function defaultExecutives()
    {
        return $this->belongsToMany(User::class, 'workflow_stage_executives', 'workflow_stage_id', 'user_id')
            ->withTimestamps();
    }
}
