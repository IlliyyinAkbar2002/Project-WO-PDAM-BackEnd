<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class TipeWorkorderFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition()
    {
        static $names = ['Normal', 'Lembur'];
        $nama = array_shift($names);

        return [
            'nama' => $nama,
        ];
    }
}
