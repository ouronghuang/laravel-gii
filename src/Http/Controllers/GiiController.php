<?php

namespace Orh\LaravelGii\Http\Controllers;

use Illuminate\Http\Request;

class GiiController
{
    public function create()
    {
        return view('gii::gii.create');
    }

    public function store(Request $request)
    {

    }
}
