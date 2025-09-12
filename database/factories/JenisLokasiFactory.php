<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class JenisLokasiFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition()
    {
        static $names = [
            'Statis',
            'Dinamis'
        ];
        $nama = array_shift($names);

        return [
            'nama' => $nama,
        ];
    }
}
