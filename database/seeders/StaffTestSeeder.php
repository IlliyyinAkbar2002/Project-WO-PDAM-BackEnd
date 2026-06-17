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
        $departemen = Departemen::firstOrCreate(['nama' => 'Operasional']);

        $jabSpv    = Jabatan::firstOrCreate(['nama' => 'Supervisor']);
        $jabSenior = Jabatan::firstOrCreate(['nama' => 'Senior Staff']);
        $jabStaff  = Jabatan::firstOrCreate(['nama' => 'Staff']);

        $role = Role::firstOrCreate(['nama' => 'Petugas Lapangan']);

        // 1 SPV — pegawai inilah yang dipakai sebagai workorder.assigned_to.
        $spv = Pegawai::updateOrCreate(
            ['nip' => 'SPV-001'],
            [
                'nama'          => 'Slamet Supervisor',
                'jenis_kelamin' => 'Laki-laki',
                'tanggal_lahir' => '1985-05-10',
                'alamat'        => 'Jl. Operasional No. 1, Surabaya',
                'telepon'       => '081200000001',
                'departemen_id' => $departemen->id,
                'jabatan_id'    => $jabSpv->id,
            ]
        );

        // Senior staff — di-assign dengan peran "koordinator" => is_pic = true (boleh submit SELESAI).
        $senior = Pegawai::updateOrCreate(
            ['nip' => 'STF-001'],
            [
                'nama'          => 'Budi Senior',
                'jenis_kelamin' => 'Laki-laki',
                'tanggal_lahir' => '1990-03-15',
                'alamat'        => 'Jl. Operasional No. 2, Surabaya',
                'telepon'       => '081200000002',
                'departemen_id' => $departemen->id,
                'jabatan_id'    => $jabSenior->id,
            ]
        );

        // Staff biasa — di-assign dengan peran "anggota".
        $staff = Pegawai::updateOrCreate(
            ['nip' => 'STF-002'],
            [
                'nama'          => 'Andi Staff',
                'jenis_kelamin' => 'Laki-laki',
                'tanggal_lahir' => '1995-07-20',
                'alamat'        => 'Jl. Operasional No. 3, Surabaya',
                'telepon'       => '081200000003',
                'departemen_id' => $departemen->id,
                'jabatan_id'    => $jabStaff->id,
            ]
        );

        $accounts = [
            ['email' => 'iyyin@gmail.com',    'pegawai' => $spv],
            ['email' => 'david123@gmail.com', 'pegawai' => $senior],
            ['email' => 'budi@gmail.com',  'pegawai' => $staff],
        ];

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
        $this->command->info("  SPV   : spv@wo.test    | pegawai_id={$spv->id}  -> pakai sbg workorder.assigned_to");
        $this->command->info("  PIC   : senior@wo.test | pegawai_id={$senior->id} -> assign peran=koordinator");
        $this->command->info("  Staff : staff@wo.test  | pegawai_id={$staff->id}  -> assign peran=anggota");
    }
}
