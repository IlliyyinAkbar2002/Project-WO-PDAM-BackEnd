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

        $jabSpv    = Jabatan::firstOrCreate(['nama' => 'Supervisor']);
        $jabSenior = Jabatan::firstOrCreate(['nama' => 'Staff Senior']);
        $jabStaff  = Jabatan::firstOrCreate(['nama' => 'Staff']);

        $role = Role::firstOrCreate(['nama' => 'employee']);

        // SPV-001 dipakai sebagai workorder.assigned_to.
        // Staff Senior di-assign dengan peran "koordinator" => is_pic = true (boleh submit SELESAI).
        // Staff biasa di-assign dengan peran "anggota".
        $pegawaiList = [
            [
                'nip'           => 'SPV-001',
                'nama'          => 'Illiyin Akbar',
                'jenis_kelamin' => 'Laki-laki',
                'tanggal_lahir' => '1985-05-10',
                'telepon'       => '081200000001',
                'jabatan_id'    => $jabSpv->id,
                'email'         => 'iyyin@gmail.com',
            ],
            [
                'nip'           => 'STF-001',
                'nama'          => 'David Jakeior',
                'jenis_kelamin' => 'Laki-laki',
                'tanggal_lahir' => '1990-03-15',
                'telepon'       => '081200000002',
                'jabatan_id'    => $jabSenior->id,
                'email'         => 'david123@gmail.com',
            ],
            [
                'nip'           => 'STF-002',
                'nama'          => 'Andi Ahmad',
                'jenis_kelamin' => 'Laki-laki',
                'tanggal_lahir' => '1995-07-20',
                'telepon'       => '081200000003',
                'jabatan_id'    => $jabStaff->id,
                'email'         => 'andi@gmail.com',
            ],
            [
                'nip'           => 'STF-003',
                'nama'          => 'Konan Ahmad',
                'jenis_kelamin' => 'Perempuan',
                'tanggal_lahir' => '1996-01-12',
                'telepon'       => '081200000004',
                'jabatan_id'    => $jabStaff->id,
                'email'         => 'konan@gmail.com',
            ],
            [
                'nip'           => 'STF-004',
                'nama'          => 'Naruto Ahmad',
                'jenis_kelamin' => 'Laki-laki',
                'tanggal_lahir' => '1995-07-25',
                'telepon'       => '081200000005',
                'jabatan_id'    => $jabStaff->id,
                'email'         => 'naruto@gmail.com',
            ],
            [
                'nip'           => 'STF-005',
                'nama'          => 'Sasuke Ahmad',
                'jenis_kelamin' => 'Laki-laki',
                'tanggal_lahir' => '1995-11-03',
                'telepon'       => '081200000006',
                'jabatan_id'    => $jabStaff->id,
                'email'         => 'sasuke@gmail.com',
            ],
            [
                'nip'           => 'STF-006',
                'nama'          => 'Kakashi Ahmad',
                'jenis_kelamin' => 'Laki-laki',
                'tanggal_lahir' => '1988-09-15',
                'telepon'       => '081200000007',
                'jabatan_id'    => $jabStaff->id,
                'email'         => 'kakashi@gmail.com',
            ],
        ];

        foreach ($pegawaiList as $i => $data) {
            $pegawai = Pegawai::updateOrCreate(
                ['nip' => $data['nip']],
                [
                    'nama'          => $data['nama'],
                    'jenis_kelamin' => $data['jenis_kelamin'],
                    'tanggal_lahir' => $data['tanggal_lahir'],
                    'alamat'        => 'Jl. Operasional No. ' . ($i + 1) . ', Surabaya',
                    'telepon'       => $data['telepon'],
                    'departemen_id' => $departemenOperasional->id,
                    'jabatan_id'    => $data['jabatan_id'],
                ]
            );

            $user = User::updateOrCreate(
                ['email' => $data['email']],
                [
                    'pegawai_id' => $pegawai->id,
                    'role_id'    => $role->id,
                    'password'   => bcrypt('password'),
                ]
            );

            // is_active tidak masuk User::$fillable, jadi harus di-set di luar mass assignment
            // supaya akun yang pernah dinonaktifkan ikut hidup lagi saat re-seed.
            $user->is_active = true;
            $user->save();
        }

        $this->command->info('StaffTestSeeder selesai. 7 akun uji (password: "password"), departemen Operasional.');
    }
}
