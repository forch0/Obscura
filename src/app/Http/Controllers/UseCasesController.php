<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class UseCasesController extends Controller
{
    public function __invoke(Request $request)
    {
        return view('use-cases');
    }
}
