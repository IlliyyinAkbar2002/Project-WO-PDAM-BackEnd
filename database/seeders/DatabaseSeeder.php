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
        MasterLocation::firstOrCreate(
        ['nama' => 'Default'],
        ['radius_meter' => 100]
    );
        Departemen::factory(3)->create();
        Jabatan::factory(6)->create();
        Role::factory(3)->create();
        Status::factory(8)->create();
        Pegawai::factory(20)->create();

        User::firstOrCreate(
    ['email' => 'superadmin@gmail.com'],
    [
        'pegawai_id' => 1,
        'role_id' => 1,
        'password' => bcrypt('password'),
    ]
);
User::firstOrCreate(
    ['email' => 'manager@gmail.com'],
    [
        'pegawai_id' => 2,
        'role_id' => 2,
        'password' => bcrypt('password'),
    ]
);
User::firstOrCreate(
    ['email' => 'employee@gmail.com'],
    [
        'pegawai_id' => 3,
        'role_id' => 3,
        'password' => bcrypt('password'),
    ]
);
        User::factory(7)->create();
        MasterKpi::factory(10)->create();
        JenisLokasi::factory(2)->create();
        TipeWorkorder::factory(2)->create();
        JenisWorkorder::factory(10)->create();
        Workorder::factory(40)->create();
        MasterAction::factory(4)->create();
    }
}
