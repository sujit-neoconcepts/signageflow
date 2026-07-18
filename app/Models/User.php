<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Mail\MeideMail;
use App\Mail\TwofaMail;
use Exception;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasApiTokens;
    use HasFactory;
    use HasRoles;
    use Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'phone',
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
                'title' => '2Factor Authentication',
                'body' => 'One Time Code: '.$code.'<br> Valid for Next 5 Minutes.',
            ];

            Mail::to(auth()->user()->email)->send(new TwofaMail($details));
        } catch (Exception $e) {
            info('Error: '.$e->getMessage());
        }
    }

    public function getRoleNameAttribute()
    {
        return $this->getRoleNames()->implode(', ');
    }

    public function getIsSuperAdminAttribute()
    {
        return $this->hasRole(env('APP_SUPER_ADMIN', 'super-admin'));
    }

    public static function neUserMail($mailcontents)
    {
        try {
            $details = [
                'title' => 'Welcome To SignageFlow CRM',
                'subject' => 'Welcome To SignageFlow CRM',
                'body' => 'Email : '.$mailcontents['user']->email.'<br>'.
                    'Password : '.$mailcontents['password'].'<br>'.
                    'IP : '.$mailcontents['ip'].'<br>'.
                    'Device : '.$mailcontents['userAgent'].'<br>',
            ];

            $superadmin = User::where('id', 1)->first();

            Mail::to($mailcontents['user']->email)->bcc($superadmin->email)->send(new MeideMail($details));
        } catch (Exception $e) {
            info('Error: '.$e->getMessage());
        }
    }

    public function tasksCreated()
    {
        return $this->hasMany(Task::class, 'creator_id');
    }

    public function tasksAssigned()
    {
        return $this->belongsToMany(Task::class, 'task_assignees', 'user_id', 'task_id')
            ->withPivot('status', 'feedback', 'completed_at')
            ->withTimestamps();
    }

    public function tasksViewing()
    {
        return $this->belongsToMany(Task::class, 'task_viewers', 'user_id', 'task_id')
            ->withTimestamps();
    }

    public function deviceTokens()
    {
        return $this->hasMany(MobileDeviceToken::class);
    }

    public function notifications()
    {
        return $this->hasMany(UserNotification::class)->latest();
    }
}
