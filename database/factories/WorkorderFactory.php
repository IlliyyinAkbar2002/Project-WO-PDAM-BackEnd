<?php

namespace Database\Factories;

use App\Models\MasterLocation;
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
        $location = MasterLocation::inRandomOrder()->first();

        // TKT-07: kolom `petugas_id` sudah di-drop — relasi WO ↔ petugas
        // sekarang lewat pivot `workorder_petugas`. Factory ini hanya
        // membuat baris WO; attach petugas dilakukan terpisah via
        // `->hasAttached(...)` atau `$workorder->petugasList()->attach([...])`
        // di seeder/tes yang memanggil factory. Dibiarkan ringkas supaya
        // factory tidak memaksakan asumsi soal user/petugas tertentu.
        return [
            'judul_pekerjaan' => $this->faker->jobTitle(),
            'waktu_penugasan' => $this->faker->dateTimeThisMonth(),
            'estimasi_durasi' => $this->faker->numberBetween(1, 23),
            'unit_waktu' => $this->faker->randomElement(['Jam', 'Hari', 'Bulan']),
            'estimasi_selesai' => $this->faker->dateTimeThisMonth(),
            'longitude' => $location?->longitude ?? $this->faker->longitude(),
            'latitude' => $location?->latitude ?? $this->faker->latitude(),
            'location_id' => $location?->id,
            'pic_id' => $this->faker->numberBetween(1, 3),
            'status_id' => $this->faker->numberBetween(1, 8),
            'jenis_workorder_id' => $this->faker->numberBetween(1, 5),
            'jenis_lokasi_id' => $this->faker->numberBetween(1, 2),
            'tipe_workorder_id' => $this->faker->numberBetween(1, 2),
        ];
    }
}
