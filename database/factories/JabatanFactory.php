<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class JabatanFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition()
    {
        // Urutan menentukan hirarki seniority (id makin kecil = makin senior),
        // dipakai oleh PegawaiController::index() via `where('id', '>', $callerJabatanId)`.
        // Daftar berjumlah 6 supaya `Jabatan::factory(6)->create()` di DatabaseSeeder
        // tidak meledak karena array_shift mengembalikan null.
        static $names = [
            'Manager',
            'Supervisor',
            'Senior Staff',
            'Staff',
        ];
        $nama = array_shift($names) ?? 'Staff';

        return [
            'nama' => $nama,
        ];
    }
}
