<?php

namespace App\Http\Controllers;

use App\Models\MasterLocation;
use App\Models\UserLocations;
use App\Models\WorkorderAssignment;
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

    /**
     * Hitung jarak dua titik koordinat (dalam meter) dengan formula haversine.
     *
     * Dipakai untuk geofencing: bandingkan hasilnya dengan radius_meter
     * milik m_location untuk menentukan apakah pegawai berada di dalam area.
     */
    private function haversineMeters($lat1, $lon1, $lat2, $lon2)
    {
        $earthRadius = 6371000; // radius bumi rata-rata dalam meter

        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);

        $a = sin($dLat / 2) * sin($dLat / 2)
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2))
            * sin($dLon / 2) * sin($dLon / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadius * $c;
    }


    // public function membersAtLocation(Request $request, int $workorderId)
    // {
    //     $assignment = WorkorderAssignment::with([
    //         'location',
    //         'members.pegawai.user',
    //     ])->where('workorder_id', $workorderId)->firstOrFail();

    //     abort_if(
    //         (int) $assignment->spv_pegawai_id !== (int) $request->user()->pegawai_id,
    //         403,
    //         'Anda bukan SPV untuk work order ini.'
    //     );

    //     abort_if(! $assignment->location, 422, 'Lokasi work order belum ditentukan.');

    //     $location = $assignment->location;

    //     $members = $assignment->members->map(function ($member) use ($location) {
    //         $pegawai = $member->pegawai;
    //         $user = $pegawai?->user;

    //         $latestLocation = $user
    //             ? UserLocations::where('user_id', $user->id)
    //                 ->latest('created_at')
    //                 ->first()
    //             : null;

    //         $distance = $latestLocation
    //             ? $this->haversineMeters(
    //                 $location->latitude,
    //                 $location->longitude,
    //                 $latestLocation->latitude,
    //                 $latestLocation->longitude
    //             )
    //             : null;

    //         return [
    //             'pegawai_id'     => $pegawai?->id,
    //             'nama'           => $pegawai?->nama,
    //             'peran'          => $member->peran,
    //             'inside'         => $distance !== null
    //                 && $distance <= $location->radius_meter,
    //             'distance_meter' => $distance !== null
    //                 ? round($distance, 2)
    //                 : null,
    //             'last_location'  => $latestLocation?->created_at,
    //         ];
    //     });

    //     return response()->json([
    //         'location' => $location,
    //         'members'  => $members,
    //         'inside'   => $members->where('inside', true)->values(),
    //     ]);
    // }
}
