<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class RoleFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition()
    {
        static $names = ['superadmin', 'manager', 'employee'];
        $nama = array_shift($names);

        return [
            'nama' => $nama,
        ];
    }
}
