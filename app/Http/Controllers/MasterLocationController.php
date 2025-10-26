<?php

namespace App\Http\Controllers;

use App\Models\MasterLocation;
use App\Models\UserLocations;
use Illuminate\Http\Request;
use function React\Promise\all;

class MasterLocationController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        try {
            $masters = MasterLocation::all();
            $userLocation = null;
            
            if ($request->user()) {
                $userLocation = UserLocations::where('user_id', $request->user()->id)->latest()->first();
            }

            // tambahkan flag inside pada tiap master
            $masters = $masters->map(function($m) use ($userLocation) {
                $m->inside = false;
                if ($userLocation && $m->latitude && $m->longitude && $m->radius_meter) {
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
                'success' => true,
                'data' => $masters,
                'user_location' => $userLocation
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Terjadi kesalahan saat mengambil data lokasi',
                'message' => $e->getMessage()
            ], 500);
        }
    }    

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'nama' => 'required|string|max:255',
            'latitude' => 'required|numeric',
            'longitude' => 'required|numeric',
            'radius_meter' => 'required|integer|min:1',
        ]);
        $location = MasterLocation::create($validated);

        return response()->json($location, 201);
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        try {
            $location = MasterLocation::findOrFail($id);
            return response()->json($location, 200);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Terjadi kesalahan saat mengambil data lokasi',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        $validatedData = $request->validate([
            'nama' => 'nullable|string|max:255',
            'latitude' => 'required|numeric',
            'longitude' => 'required|numeric',
            'radius_meter' => 'required|integer|min:1',
        ]);

        try {
            $location = MasterLocation::findOrFail($id);
            $location->update($validatedData);
            return response()->json([
                'message' => 'Lokasi berhasil diperbarui',
                'data' => $location,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Terjadi kesalahan saat memperbarui lokasi',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        //
    }

    // helper
    private function haversineMeters($lat1, $lon1, $lat2, $lon2) {
        $R = 6371000;
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);
        $a = sin($dLat/2) * sin($dLat/2) + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon/2) * sin($dLon/2);
        $c = 2 * atan2(sqrt($a), sqrt(1-$a));
        return $R * $c;
    }
}
