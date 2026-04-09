<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EnquiryFile extends Model
{
    use HasFactory;

    protected $fillable = [
        'enquiry_id',
        'original_name',
        'stored_name',
        'mime_type',
        'file_size',
    ];

    public function enquiry()
    {
        return $this->belongsTo(Enquiry::class);
    }

    /**
     * Storage path relative to disk root.
     */
    public function storagePath(): string
    {
        return 'enquiry_files/' . $this->enquiry_id . '/' . $this->stored_name;
    }
}
