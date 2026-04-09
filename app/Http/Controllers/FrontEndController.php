<?php

namespace App\Http\Controllers;

use App\Models\Portfolio;
use App\Models\Page;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class FrontEndController extends Controller
{
    public function home()
    {

        return  view('home');
    }

}
