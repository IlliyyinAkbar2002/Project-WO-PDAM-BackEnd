<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class PegawaiFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition()
    {

        $kodeGolongan = rand(1, 4);
        $tahunMasuk = rand(90, 99);
        $nomorUrut = str_pad(rand(1, 99999), 5, '0', STR_PAD_LEFT);
        $nip = "{$kodeGolongan}." . str_pad($tahunMasuk, 2, '0', STR_PAD_LEFT) . ".{$nomorUrut}";

        return [
            'nama' => $this->faker->name(),
            'nip' => $nip,
            'tanggal_lahir' => $this->faker->date(),
            'jenis_kelamin' => $this->faker->randomElement(['Laki-laki', 'Perempuan']),
            'alamat' => $this->faker->address(),
            'telepon' => $this->faker->phoneNumber(),
            'departemen_id' => $this->faker->numberBetween(1, 3),
            'jabatan_id' => $this->faker->numberBetween(1, 4),
        ];
    }
}
