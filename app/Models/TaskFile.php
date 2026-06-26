<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class TaskFile extends Model
{
    use HasFactory;

    protected $fillable = [
        'task_id',
        'file_path',
        'file_name',
        'file_type',
        'file_size',
    ];

    protected $appends = ['url'];

    public function task()
    {
        return $this->belongsTo(Task::class);
    }

    public function getUrlAttribute()
    {
        return Storage::url($this->file_path);
    }
}
