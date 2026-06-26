<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class TaskCommentFile extends Model
{
    use HasFactory;

    protected $fillable = [
        'task_comment_id',
        'file_path',
        'file_name',
        'file_type',
        'file_size',
    ];

    protected $appends = ['url'];

    public function comment()
    {
        return $this->belongsTo(TaskComment::class, 'task_comment_id');
    }

    public function getUrlAttribute()
    {
        return Storage::url($this->file_path);
    }
}
