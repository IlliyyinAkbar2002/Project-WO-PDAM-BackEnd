<?php

namespace App\Http\Controllers;

use App\Models\UserLocations;
use Illuminate\Http\Request;

class LocationController extends Controller
{
    public function store(Request $request)
{
    $request->validate([
        'latitude' => 'required|numeric',
        'longitude' => 'required|numeric',
        'accuracy' => 'nullable|numeric',
    ]);

    $user = $request->user(); // harus auth
    $loc = UserLocations::create([
        'user_id' => $user->id,
        'latitude' => $request->latitude,
        'longitude' => $request->longitude,
        'accuracy' => $request->accuracy,
    ]);

    // optional: broadcast event
    // event(new \App\Events\UserMoved($user, $loc));

    return response()->json($loc, 201);
}

}
