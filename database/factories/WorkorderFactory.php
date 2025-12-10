<?php

namespace Database\Factories;

use App\Models\MasterLocation;
use App\Models\User;
use App\Models\Workorder;
use Illuminate\Database\Eloquent\Factories\Factory;

class WorkorderFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition()
    {
        // Ambil random MasterLocation untuk mendapatkan koordinat dan location_id
        $location = MasterLocation::inRandomOrder()->first();
        
        return [
            'judul_pekerjaan' => $this->faker->jobTitle(),
            'waktu_penugasan' => $this->faker->dateTimeThisMonth(),
            'estimasi_durasi' => $this->faker->numberBetween(1, 23),
            'unit_waktu' => $this->faker->randomElement(['Jam', 'Hari', 'Bulan']),
            'estimasi_selesai' => $this->faker->dateTimeThisMonth(),
            'longitude' => $location?->longitude ?? $this->faker->longitude(),
            'latitude' => $location?->latitude ?? $this->faker->latitude(),
            'location_id' => $location?->id,
            'petugas_id' => $this->faker->numberBetween(4, 5), // Users 4 (David) and 5 (Budi) are employees
            'pic_id' => $this->faker->numberBetween(1, 3),   // Users 1-3 are Admin/Managers
            'status_id' => $this->faker->numberBetween(1, 8),
            'jenis_workorder_id' => $this->faker->numberBetween(1, 5),
            'jenis_lokasi_id' => $this->faker->numberBetween(1, 2),
            'tipe_workorder_id' => $this->faker->numberBetween(1, 2),
        ];
    }
}
