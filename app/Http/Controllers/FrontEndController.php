<?php

namespace App\Http\Controllers;

class FrontEndController extends Controller
{
    public function home()
    {

        return view('home');
    }
}
