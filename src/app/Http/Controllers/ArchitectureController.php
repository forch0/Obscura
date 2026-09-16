<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class ArchitectureController extends Controller
{
    public function __invoke(Request $request)
    {
        return view('architecture');
    }
}
