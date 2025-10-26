<?php

namespace Database\Seeders;

use App\Models\Departemen;
use App\Models\Jabatan;
use App\Models\JenisLokasi;
use App\Models\JenisWorkorder;
use App\Models\MasterAction;
use App\Models\MasterKpi;
use App\Models\MasterLocation;
use App\Models\Pegawai;
use App\Models\Pic;
use App\Models\Role;
use App\Models\Status;
use App\Models\TipeWorkorder;
use App\Models\User;
use App\Models\Workorder;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * @return void
     */
    public function run()
    {
        MasterLocation::factory(1)->create([
            'nama' => 'PDAM Surya Sembada Kota Surabaya',
            'latitude' => -7.2654798,
            'longitude' => 112.754074,
            'radius_meter' => 100,
        ]);
        
        MasterLocation::factory(1)->create([
            'nama' => 'Ciputra World Surabaya',
            'latitude' => -7.2925952,
            'longitude' => 112.7200837,
            'radius_meter' => 150,
        ]);
        
        MasterLocation::factory(1)->create([
            'nama' => 'Telkom Universitas Surabaya',
            'latitude' => -7.3111665,
            'longitude' => 112.728915,
            'radius_meter' => 200,
        ]);
        Departemen::factory(3)->create();
        Jabatan::factory(6)->create();
        Role::factory(3)->create();
        Status::factory(8)->create();
        Pegawai::factory(20)->create();

        User::factory()->create([
            'pegawai_id' => 1,
            'role_id' => 1,
            'email' => 'superadmin@gmail.com',
            'password' => bcrypt('password'),
        ]);
        User::factory()->create([
            'pegawai_id' => 2,
            'role_id' => 2,
            'email' => 'david123@gmail.com',
            'password' => bcrypt('password'),
        ]);
        User::factory()->create([
            'pegawai_id' => 3,
            'role_id' => 3,
            'email' => 'budi123@gmail.com',
            'password' => bcrypt('password'),
        ]);
        User::factory(7)->create();
        MasterKpi::factory(10)->create();
        JenisLokasi::factory(2)->create();
        TipeWorkorder::factory(2)->create();
        JenisWorkorder::factory(10)->create();
        Workorder::factory(40)->create();
        MasterAction::factory(4)->create();
    }
}
