<?php

namespace App\Http\Controllers;

use App\Models\MasterLocation;
use App\Models\UserLocations;
use Illuminate\Http\Request;

class LocationController extends Controller
{
    public function store(Request $request)
    {
        $validated = $request->validate([
                'latitude' => 'required|numeric',
                'longitude' => 'required|numeric',
                'accuracy' => 'nullable|numeric',
            ]);

            $user = $request->user();

            $location = UserLocations::create([
                'user_id' => $user->id,
                'latitude' => $validated['latitude'],
                'longitude' => $validated['longitude'],
                'accuracy' => $validated['accuracy'] ?? null,
            ]);

            return response()->json($location, 201);
    }

    public function latest(Request $request)
    {
        $user = $request->user();
        $location = UserLocations::where('user_id', $user->id)->latest()->first();

        return response()->json($location);
    }

    public function all()
    {
        // untuk pantau semua user (misalnya admin)
        $locations = UserLocations::latest()->get()->groupBy('user_id')->map->first();
        return response()->json($locations);
    }

    public function check(Request $request)
    {
        $masters = MasterLocation::all();
        $userLocation = UserLocations::where('user_id', $request->user()->id)->latest()->first();

        $masters = $masters->map(function ($m) use ($userLocation) {
            $m->inside = false;
            if ($userLocation) {
                $meters = $this->haversineMeters(
                    $m->latitude,
                    $m->longitude,
                    $userLocation->latitude,
                    $userLocation->longitude
                );
                $m->inside = $meters <= $m->radius_meter;
            }
            return $m;
        });

        return response()->json([
            'master_locations' => $masters,
            'user_location' => $userLocation
        ]);
    }

}
