<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\OptionForm;

class OptionformController extends Controller
{
    //

    public function index()
    {
        $optionforms = OptionForm::all();
        return response()->json($optionforms, 200);
    }

    public function store(Request $request)
    {
        $optionform = OptionForm::create($request->all());
        return response()->json($optionform, 201);
    }
}
