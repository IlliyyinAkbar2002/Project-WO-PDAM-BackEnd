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
        static $names = [
            'Kepala Departemen',
            'Manager Senior',
            'Manager',
            'Supervisor',
            'Staff Senior',
            'Staff'
        ];
        $nama = array_shift($names);

        return [
            'nama' => $nama,
        ];
    }
}
