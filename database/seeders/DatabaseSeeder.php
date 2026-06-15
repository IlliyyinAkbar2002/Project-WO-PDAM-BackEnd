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
use Illuminate\Support\Facades\DB;
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
        MasterLocation::updateOrCreate(
            ['nama' => 'PERUMDA Air Minum Surya Sembada Kota Surabaya'],
            [
                'latitude' => -7.2654798,
                'longitude' => 112.754074,
                'radius_meter' => 50,
            ]
        );
        MasterLocation::updateOrCreate(
            ['nama' => 'PDAM IPAM Karangpilang'],
            [
                'latitude' => -7.347395836428751,
                'longitude' => 112.6821334744601,
                'radius_meter' => 100,
            ]
        );
        MasterLocation::updateOrCreate(
            ['nama' => 'PDAM Ngagel Tirto'],
            [
                'latitude' => -7.299541498200452,
                'longitude' => 112.74518509673882,
                'radius_meter' => 150,
            ]
        );

        Departemen::factory(3)->create();
        Jabatan::factory(6)->create();
        Role::factory(4)->create();

        Pegawai::updateOrCreate(['id' => 1], [
        'nama' => 'Super Admin',
        'nip' => '1.23.45678',
        'tanggal_lahir' => '1980-01-01',
        'jenis_kelamin' => 'Laki-laki',
        'alamat' => 'Jl. Surabaya No.1',
        'telepon' => '081234567890',
        'departemen_id' => 1,
        'jabatan_id' => 1,
        ]);

        Pegawai::updateOrCreate(['id' => 2], [
        'nama' => 'Admin Baik',
        'nip' => '4.99.70756',
        'tanggal_lahir' => '1985-02-02',
        'jenis_kelamin' => 'Perempuan',
        'alamat' => 'Jl. Surabaya No.2',
        'telepon' => '081234567891',
        'departemen_id' => 2,
        'jabatan_id' => 2,
        ]);

        Pegawai::updateOrCreate(['id' => 3], [
        'nama' => 'Manager Baik',
        'nip' => '4.98.70756',
        'tanggal_lahir' => '1985-03-02',
        'jenis_kelamin' => 'Laki-laki',
        'alamat' => 'Jl. Surabaya No.5',
        'telepon' => '081234567891',
        'departemen_id' => 2,
        'jabatan_id' => 2,
        ]);

        // Pastikan sequence id m_pegawai mengikuti nilai maksimum saat ini
        DB::statement("SELECT setval(pg_get_serial_sequence('m_pegawai', 'id'), (SELECT COALESCE(MAX(id), 0) FROM m_pegawai))");

        Pegawai::factory(20)->create();

        User::updateOrCreate([
            'pegawai_id' => 1,
            'role_id' => 1,
            'email' => 'superadmin@gmail.com',
            'password' => bcrypt('password'),
        ]);
        User::updateOrCreate([
            'pegawai_id' => 2,
            'role_id' => 2,
            'email' => 'admin@gmail.com',
            'password' => bcrypt('password'),
        ]);
        User::updateOrCreate([
            'pegawai_id' => 3,
            'role_id' => 3,
            'email' => 'manager@gmail.com',
            'password' => bcrypt('password'),
        ]);
        User::updateOrCreate([
            'pegawai_id' => 4,
            'role_id' => 3,
            'email' => 'employee@gmail.com',
            'password' => bcrypt('password'),
        ]);
        User::updateOrCreate([
            'pegawai_id' => 5,
            'role_id' => 3,
            'email' => 'david123@gmail.com',
            'password' => bcrypt('password'),
        ]);
        User::updateOrCreate([
            'pegawai_id' => 6,
            'role_id' => 3,
            'email' => 'budi123@gmail.com',
            'password' => bcrypt('password'),
        ]);
        User::factory(7)->create();
        JenisLokasi::factory(2)->create();
        TipeWorkorder::factory(2)->create();
        JenisWorkorder::factory(9)->create();
        MasterAction::factory(4)->create();
    }
}
