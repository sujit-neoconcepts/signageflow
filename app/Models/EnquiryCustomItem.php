<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EnquiryCustomItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'enquiry_id',
        'item_name',
        'qty',
    ];

    public function enquiry()
    {
        return $this->belongsTo(Enquiry::class);
    }
}
