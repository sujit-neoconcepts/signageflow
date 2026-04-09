<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

use Exception;

use Illuminate\Support\Facades\Mail;
use App\Mail\TwofaMail;
use App\Mail\MeideMail;

class User extends Authenticatable
{
    use HasApiTokens;
    use HasFactory;
    use Notifiable;
    use HasRoles;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'twofa',
        'password',
    ];

    protected $appends = [];
    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
    ];

    public function setPasswordAttribute($password)
    {
        $this->attributes['password'] = bcrypt($password);
    }

    public function generateCode()
    {
        $code = rand(100000, 999999);

        UserEmailCode::updateOrCreate(
            ['user_id' => auth()->user()->id],
            ['code' => $code]
        );

        try {

            $details = [
                'title' => '2Factor Athentication',
                'body' => 'One Time Code: ' . $code . '<br> Valid for Next 5 Minutes.'
            ];

            Mail::to(auth()->user()->email)->send(new TwofaMail($details));
        } catch (Exception $e) {
            info("Error: " . $e->getMessage());
        }
    }

    public function getRoleNameAttribute()
    {
        return $this->getRoleNames()[0] ?? '';
    }


    public static function neUserMail($mailcontents)
    {
        try {
            $details = [
                'title' => 'Welcome To SignageFlow CRM',
                'subject' => 'Welcome To SignageFlow CRM',
                'body' =>   'Email : ' . $mailcontents['user']->email . '<br>' .
                    'Password : ' . $mailcontents['password'] . '<br>' .
                    'IP : ' . $mailcontents['ip'] . '<br>' .
                    'Device : ' . $mailcontents['userAgent'] . '<br>'
            ];

            $superadmin = User::where('id', 1)->first();

            Mail::to($mailcontents['user']->email)->bcc($superadmin->email)->send(new MeideMail($details));
        } catch (Exception $e) {
            info("Error: " . $e->getMessage());
        }
    }
}
