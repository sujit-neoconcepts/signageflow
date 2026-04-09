<?php

namespace App\Models;

use Illuminate\Support\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class LogActivity extends Model
{
    use HasFactory;
    protected $fillable = [
        'user_id',
        'action',
        'module',
        'data_key',
        'comment',
        'ip',
        'user_agent'
    ];
    protected $appends = ["formatted_date"];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
    public function getUserNameAttribute()
    {
        return $this->relationLoaded('user') ? ($this->user->name ?? '') : '';
    }

    public function getFormattedDateAttribute()
    {
        return $this->created_at ? \Carbon\Carbon::parse($this->created_at)->format('d-m-Y: H:i:s') : '';
    }
    public function scopeActivityStartDate($query, $sd)
    {
        $start = ($sd instanceof Carbon) ? $sd : Carbon::parse($sd);
        return $query->where('created_at', '>=', $start->startOfDay());
    }
    public function scopeActivityEndDate($query, $ed)
    {
        $end   = ($ed instanceof Carbon) ? $ed : Carbon::parse($ed);
        return $query->where('created_at', '<=', $end->endOfDay());
    }
}
