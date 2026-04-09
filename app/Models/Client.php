<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Auth\User as Authenticatable;

class Client extends Authenticatable
{
    use HasFactory;
    protected $fillable = ['cl_name', 'contact_person', 'cl_addr', 'cl_addr2', 'pincode', 'cl_phn', 'cl_email', 'password', 'cl_gst', 'active'];
    protected $appends = ['active_status'];
    protected $hidden = ['password',  'remember_token'];

    public function setPasswordAttribute($password)
    {
        $this->attributes['password'] = bcrypt($password);
    }
    public function getActiveStatusAttribute()
    {
        return $this->active == 1 ? 'Yes' : 'No';
    }


    // Relationships
    public function sales()
    {
        return $this->hasMany(Sales::class);
    }

    public function sheetSales()
    {
        return $this->hasMany(SheetSales::class);
    }

    public function coilSales()
    {
        return $this->hasMany(CoilSales::class);
    }

    public function stripSales()
    {
        return $this->hasMany(StripSales::class);
    }

    public function blusterSales()
    {
        return $this->hasMany(BlusterSales::class);
    }

    public function blackTubeSales()
    {
        return $this->hasMany(BlackTubeSales::class);
    }

    public function enquiries()
    {
        return $this->hasMany(Enquiry::class);
    }

    public function sheetEnquiries()
    {
        return $this->hasMany(SheetEnquiry::class);
    }

    public function coilEnquiries()
    {
        return $this->hasMany(CoilEnquiry::class);
    }

    public function stripEnquiries()
    {
        return $this->hasMany(StripEnquiry::class);
    }

    public function blusterEnquiries()
    {
        return $this->hasMany(BlusterEnquiry::class);
    }

    public function blackTubeEnquiries()
    {
        return $this->hasMany(BlackTubeEnquiry::class);
    }


    public static function formInfo()
    {
        $formInfo = [
            'cl_name' => ['label' => 'Client Name', 'vRule' => 'required|unique:clients,cl_name'],
            'contact_person' => ['label' => 'Contact Person'],
            'cl_addr' => ['label' => 'Address'],
            'cl_addr2' => ['label' => 'Address Line 2'],
            'pincode' => ['label' => 'Pincode'],
            'cl_phn' => ['label' => 'Phone'],
            'cl_email' => ['label' => 'Email', 'vRule' => 'nullable|email'],
            'password' => ['label' => 'Password', 'type' => 'password', 'tooltip' => "Leave Blank in edit mode if not to change Password"],
            'cl_gst' => ['label' => 'GST'],
            'active' => ['label' => 'Active', 'type' => 'select', 'options' => [['id' => 0, 'label' => 'No'], ['id' => 1, 'label' => 'Yes'],]],
        ];

        return $formInfo;
    }
}
