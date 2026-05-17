<?php

namespace Database\Factories;

use App\Models\Workorder;
use Illuminate\Database\Eloquent\Factories\Factory;

class WorkorderFactory extends Factory
{
    protected $model = Workorder::class;

    /**
     * Define the model's default state.
     *
     * Sesuai restrukturisasi Mei 2026:
     * - Kolom timeline (estimasi_durasi, unit_waktu, estimasi_selesai) sudah
     *   dipindah ke workorder_assignment (diisi SPV saat assign staff).
     * - Kolom lokasi geo (latitude, longitude, location_id) juga pindah ke
     *   workorder_assignment.
     * - Kolom tipe_workorder_id, jenis_lokasi_id sudah di-drop.
     * - Kolom petugas_id sudah di-drop (relasi via wo_assignment_member).
     *
     * @return array
     */
    public function definition()
    {
        return [
            'nama_workorder'     => $this->faker->sentence(4),
            'deskripsi'          => $this->faker->paragraph(),
            'tanggal_mulai'      => $this->faker->dateTimeThisMonth(),
            'lokasi'             => $this->faker->address(),
            'prioritas'          => $this->faker->randomElement(['rendah', 'sedang', 'tinggi']),
            'assigned_to'        => $this->faker->numberBetween(1, 3),
            'created_by_user_id' => 1,
            'status_id'          => $this->faker->numberBetween(1, 8),
            'jenis_workorder_id' => $this->faker->numberBetween(1, 5),
        ];
    }
}
