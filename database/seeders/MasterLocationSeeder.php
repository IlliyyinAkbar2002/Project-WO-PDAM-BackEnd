<?php

namespace Database\Seeders;

use App\Models\MasterLocation;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class MasterLocationSeeder extends Seeder
{
    public function run(): void
    {
        $locations = [
            [
                'nama' => 'PDAM Surya Sembada Kota Surabaya',
                'latitude' => -7.2654798,
                'longitude' => 112.754074,
                'radius_meter' => 100,
            ],
            [
                'nama' => 'Ciputra World Surabaya',
                'latitude' => -7.2925952,
                'longitude' => 112.7200837,
                'radius_meter' => 150,
            ],
            [
                'nama' => 'Telkom Universitas Surabaya',
                'latitude' => -7.3111665,
                'longitude' => 112.728915,
                'radius_meter' => 200,
            ],
            [
                'nama' => 'Perum Lestari Indah',
                'latitude' => -7.3482089,
                'longitude' => 112.5987654,
                'radius_meter' => 100,
            ],
        ];

        DB::transaction(function () use ($locations) {
            $this->removeDuplicateSeedLocations($locations);

            foreach ($locations as $location) {
                MasterLocation::updateOrCreate(
                    [
                        'nama' => $location['nama'],
                        'latitude' => $location['latitude'],
                        'longitude' => $location['longitude'],
                    ],
                    [
                        'radius_meter' => $location['radius_meter'],
                    ]
                );
            }
        });
    }

    private function removeDuplicateSeedLocations(array $locations): void
    {
        foreach ($locations as $location) {
            $locationIds = MasterLocation::query()
                ->where('nama', $location['nama'])
                ->where('latitude', $location['latitude'])
                ->where('longitude', $location['longitude'])
                ->orderBy('id')
                ->pluck('id');

            if ($locationIds->count() <= 1) {
                continue;
            }

            $keepId = $locationIds->shift();
            $duplicateIds = $locationIds;

            if ($duplicateIds->isEmpty()) {
                continue;
            }

            DB::table('workorder')
                ->whereIn('location_id', $duplicateIds)
                ->update(['location_id' => $keepId]);

            MasterLocation::query()
                ->whereIn('id', $duplicateIds)
                ->delete();
        }
    }
}
