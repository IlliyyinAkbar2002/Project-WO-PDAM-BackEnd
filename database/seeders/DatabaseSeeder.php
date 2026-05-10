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
        // $this->call(MasterLocationSeeder::class);
        // Disabled: lokasi sekarang dikirim dari FE Mobile, bukan seeder.
        Departemen::factory(3)->create();
        Jabatan::factory(6)->create();
        Role::factory(3)->create();
        $this->call(StatusSeeder::class);

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
        'nama' => 'Geo Anak baik',
        'nip' => '4.99.70756',
        'tanggal_lahir' => '1985-02-02',
        'jenis_kelamin' => 'Perempuan',
        'alamat' => 'Jl. Surabaya No.2',
        'telepon' => '081234567891',
        'departemen_id' => 2,
        'jabatan_id' => 2,
        ]);

        DB::statement("SELECT setval(pg_get_serial_sequence('m_pegawai', 'id'), (SELECT COALESCE(MAX(id), 0) FROM m_pegawai))");

        Pegawai::factory(20)->create();

        // Pegawai 1: Super Admin
        Pegawai::updateOrCreate(
            [
                'nama' => 'Super Admin',
                'nip' => '1234567891',
                'tanggal_lahir' => '1985-01-01',
                'jenis_kelamin' => 'Laki-laki',
                'alamat' => 'Jl. Admin No. 1',
                'telepon' => '081234567891',
                'departemen_id' => 2,
                'jabatan_id' => 1,
            ]
        );

        // Pegawai 2: Satoshi (Manager)
        Pegawai::updateOrCreate(
            [
                'nama' => 'Satoshi',
                'nip' => '1234567892',
                'tanggal_lahir' => '1990-01-01',
                'jenis_kelamin' => 'Laki-laki',
                'alamat' => 'Jl. Geo No. 1',
                'telepon' => '081234567892',
                'departemen_id' => 2,
                'jabatan_id' => 3,
            ]
        );

        // Pegawai 3: Illiyyin (Employee as jabatan SPV)
        Pegawai::updateOrCreate(
            [
                'nama' => 'Illiyyin',
                'nip' => '1234567832',
                'tanggal_lahir' => '1990-01-01',
                'jenis_kelamin' => 'Laki-laki',
                'alamat' => 'Jl. Geo No. 1',
                'telepon' => '081234567892',
                'departemen_id' => 2,
                'jabatan_id' => 4,
            ]
        );

        // Pegawai 4: Aulya (Employee as jabatan SPV)
        Pegawai::updateOrCreate(
            [
                'nama' => 'Aulya',
                'nip' => '1234567893',
                'tanggal_lahir' => '1990-01-01',
                'jenis_kelamin' => 'Laki-laki',
                'alamat' => 'Jl. Manager No. 1',
                'telepon' => '081234567893',
                'departemen_id' => 2,
                'jabatan_id' => 4,
            ]
        );

        // Pegawai 5: David (Employee as jabatan Staff)
        Pegawai::updateOrCreate(
            [
                'nama' => 'David',
                'nip' => '1234567894',
                'tanggal_lahir' => '1990-01-01',
                'jenis_kelamin' => 'Laki-laki',
                'alamat' => 'Jl. David No. 1',
                'telepon' => '081234567894',
                'departemen_id' => 2,
                'jabatan_id' => 5,
            ]
        );

        // Pegawai 6: Budi (Employee as jabatan Staff)
        Pegawai::updateOrCreate(
            [
                'nama' => 'Budi',
                'nip' => '1234567895',
                'tanggal_lahir' => '1990-01-01',
                'jenis_kelamin' => 'Laki-laki',
                'alamat' => 'Jl. Budi No. 1',
                'telepon' => '081234567895',
                'departemen_id' => 2,
                'jabatan_id' => 6,
            ]
        );

        // Buat User untuk setiap Pegawai (role_id ada di tabel users, bukan pegawai)
        User::updateOrCreate(
            ['email' => 'superadmin@gmail.com'],
            [
                'pegawai_id' => Pegawai::where('nip', '1234567891')->first()->id,
                'role_id' => 1, // Super Admin
                'password' => bcrypt('password'),
            ]
        );

        User::updateOrCreate(
            ['email' => 'satoshi@gmail.com'],
            [
                'pegawai_id' => Pegawai::where('nip', '1234567892')->first()->id,
                'role_id' => 2, // Manager as jabatan Manager
                'password' => bcrypt('password'),
            ]
        );

        User::updateOrCreate(
            ['email' => 'iyyin@gmail.com'],
            [
                'pegawai_id' => Pegawai::where('nip', '1234567832')->first()->id,
                'role_id' => 3, // Employee as jabatan SPV
                'password' => bcrypt('password'),
            ]
        );

        User::updateOrCreate(
            ['email' => 'aulya@gmail.com'],
            [
                'pegawai_id' => Pegawai::where('nip', '1234567893')->first()->id,
                'role_id' => 3, // Employee as jabatan SPV
                'password' => bcrypt('password'),
            ]
        );

        User::updateOrCreate(
            ['email' => 'david123@gmail.com'],
            [
                'pegawai_id' => Pegawai::where('nip', '1234567894')->first()->id,
                'role_id' => 3, // Employee as jabatan Staff
                'password' => bcrypt('password'),
            ]
        );

        User::updateOrCreate(
            ['email' => 'budi123@gmail.com'],
            [
                'pegawai_id' => Pegawai::where('nip', '1234567895')->first()->id,
                'role_id' => 3, // Employee as jabatan Staff
                'password' => bcrypt('password'),
            ]
        );
        MasterKpi::factory(10)->create();
        JenisLokasi::factory(2)->create();
        TipeWorkorder::factory(2)->create();
        MasterAction::factory(4)->create();
        $this->call(TipeProgressSeeder::class);
        JenisWorkorder::factory(10)->create();
    }
}
