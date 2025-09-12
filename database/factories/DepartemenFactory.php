<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class DepartemenFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition()
    {
        static $names = ['Operasional', 'Pelayanan', 'Keuangan'];
        $nama = array_shift($names);

        return [
            'nama' => $nama,
        ];
    }
}
