<?php

namespace Database\Seeders;

use App\Models\Departemen;
use App\Models\Jabatan;
use App\Models\Pegawai;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;


class StaffTestSeeder extends Seeder
{
    public function run(): void
    {
        $departemenOperasional = Departemen::firstOrCreate(['nama' => 'Operasional']);
        $departemenPelayanan   = Departemen::firstOrCreate(['nama' => 'Pelayanan']);

        $jabSpv    = Jabatan::firstOrCreate(['nama' => 'Supervisor']);
        $jabSenior = Jabatan::firstOrCreate(['nama' => 'Staff Senior']);
        $jabStaff  = Jabatan::firstOrCreate(['nama' => 'Staff']);

        $role = Role::firstOrCreate(['nama' => 'employee']);

        // 1 SPV — pegawai inilah yang dipakai sebagai workorder.assigned_to.
        $spv = Pegawai::updateOrCreate(
            ['nip' => 'SPV-001'],
            [
                'nama'          => 'Illiyin Akbar',
                'jenis_kelamin' => 'Laki-laki',
                'tanggal_lahir' => '1985-05-10',
                'alamat'        => 'Jl. Operasional No. 1, Surabaya',
                'telepon'       => '081200000001',
                'departemen_id' => $departemenOperasional->id,
                'jabatan_id'    => $jabSpv->id,
            ]
        );

        // Senior staff — di-assign dengan peran "koordinator" => is_pic = true (boleh submit SELESAI).
        $senior = Pegawai::updateOrCreate(
            ['nip' => 'STF-001'],
            [
                'nama'          => 'David Jakeior',
                'jenis_kelamin' => 'Laki-laki',
                'tanggal_lahir' => '1990-03-15',
                'alamat'        => 'Jl. Operasional No. 2, Surabaya',
                'telepon'       => '081200000002',
                'departemen_id' => $departemenOperasional->id,
                'jabatan_id'    => $jabSenior->id,
            ]
        );

        // Staff biasa — di-assign dengan peran "anggota".
        $staff = Pegawai::updateOrCreate(
            ['nip' => 'STF-002'],
            [
                'nama'          => 'Andi Ahmad',
                'jenis_kelamin' => 'Laki-laki',
                'tanggal_lahir' => '1995-07-20',
                'alamat'        => 'Jl. Operasional No. 3, Surabaya',
                'telepon'       => '081200000003',
                'departemen_id' => $departemenOperasional->id,
                'jabatan_id'    => $jabStaff->id,
            ]
        );

        $accounts = [
            ['email' => 'iyyin@gmail.com',    'pegawai' => $spv],
            ['email' => 'david123@gmail.com', 'pegawai' => $senior],
            ['email' => 'andi@gmail.com',  'pegawai' => $staff],
        ];

        $spvPelayanan = Pegawai::updateOrCreate(
            ['nip' => 'PLY-SPV-001'],
            [
                'nama'          => 'Aulya',
                'jenis_kelamin' => 'Perempuan',
                'tanggal_lahir' => '1987-04-12',
                'alamat'        => 'Jl. Pelayanan No. 1, Surabaya',
                'telepon'       => '081200000004',
                'departemen_id' => $departemenPelayanan->id,
                'jabatan_id'    => $jabSpv->id,
            ]
        );

        $seniorPelayanan = Pegawai::updateOrCreate(
            ['nip' => 'PLY-STF-001'],
            [
                'nama'          => 'Dono Pelayanan',
                'jenis_kelamin' => 'Laki-laki',
                'tanggal_lahir' => '1991-06-18',
                'alamat'        => 'Jl. Pelayanan No. 2, Surabaya',
                'telepon'       => '081200000005',
                'departemen_id' => $departemenPelayanan->id,
                'jabatan_id'    => $jabSenior->id,
            ]
        );

        $staffPelayanan = Pegawai::updateOrCreate(
            ['nip' => 'PLY-STF-002'],
            [
                'nama'          => 'Jokowi Pelayanan',
                'jenis_kelamin' => 'Laki-laki',
                'tanggal_lahir' => '1996-09-22',
                'alamat'        => 'Jl. Pelayanan No. 3, Surabaya',
                'telepon'       => '081200000006',
                'departemen_id' => $departemenPelayanan->id,
                'jabatan_id'    => $jabStaff->id,
            ]
        );

        $accounts = array_merge($accounts, [
            ['email' => 'aul@gmail.com',    'pegawai' => $spvPelayanan],
            ['email' => 'dono@gmail.com', 'pegawai' => $seniorPelayanan],
            ['email' => 'jokowi@gmail.com',  'pegawai' => $staffPelayanan],
        ]);

        foreach ($accounts as $acc) {
            User::updateOrCreate(
                ['email' => $acc['email']],
                [
                    'pegawai_id' => $acc['pegawai']->id,
                    'role_id'    => $role->id,
                    'password'   => bcrypt('password'),
                    'is_active'  => true,
                ]
            );
        }

        $this->command->info('StaffTestSeeder selesai. Akun uji (password: "password"):');
        $this->command->info('  Departemen Operasional:');
        $this->command->info("    SPV          : iyyin@gmail.com    | pegawai_id={$spv->id}");
        $this->command->info("    Staff Senior : david123@gmail.com | pegawai_id={$senior->id}");
        $this->command->info("    Staff        : andi@gmail.com     | pegawai_id={$staff->id}");

        $this->command->info('  Departemen Pelayanan:');
        $this->command->info("    SPV          : spv.pelayanan@wo.test    | pegawai_id={$spvPelayanan->id}");
        $this->command->info("    Staff Senior : senior.pelayanan@wo.test | pegawai_id={$seniorPelayanan->id}");
        $this->command->info("    Staff        : staff.pelayanan@wo.test  | pegawai_id={$staffPelayanan->id}");
    }
}
