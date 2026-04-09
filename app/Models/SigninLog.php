<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Mail\SigninMail;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Carbon;

class SigninLog extends Model
{
    use HasFactory;
    protected $fillable = [
        'email',
        'ip',
        'msg',
        'userAgent',
    ];

    protected $appends = ['formatted_date'];

    public function getFormattedDateAttribute()
    {
        return $this->created_at ? \Carbon\Carbon::parse($this->created_at)->format('d-m-Y: H:i:s') : '';
    }

    public static function signlogMail($mailcontents)
    {
        try {
            $details = [
                'title' => 'Signin attempt Mail',
                'body' =>   'Email : ' . $mailcontents['email'] . '<br>' .
                    'IP : ' . $mailcontents['ip'] . '<br>' .
                    'Status : ' . $mailcontents['msg'] . '<br>' .
                    'Device : ' . $mailcontents['userAgent'] . '<br>'
            ];

            $superadmin = User::where('id', 1)->first();

            if ($superadmin) {
                Mail::to($superadmin->email)->send(new SigninMail($details));
            } else {
                info("Error: Super admin user with ID 1 not found. Cannot send sign-in log email.");
            }
        } catch (Exception $e) {
            info("Error: " . $e->getMessage());
        }
    }

    public function scopeSignedinStartDate($query, $sd)
    {
        $start = ($sd instanceof Carbon) ? $sd : Carbon::parse($sd);
        return $query->where('created_at', '>=', $start->startOfDay());
    }
    public function scopeSignedinEndDate($query, $ed)
    {
        $end   = ($ed instanceof Carbon) ? $ed : Carbon::parse($ed);
        return $query->where('created_at', '<=', $end->endOfDay());
    }
}
