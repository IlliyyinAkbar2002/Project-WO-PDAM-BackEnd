<?php

namespace App\Services;

use App\Models\LemburSpl;
use App\Models\MasterAction;
use App\Models\Workorder;
use Illuminate\Support\Facades\DB;

class WorkorderService
{
  /**
   * Membuat 1 workorder lalu attach N petugas via pivot `workorder_petugas`.
   *
   * Sebelum TKT-07: satu pengajuan dengan N petugas membuat **N row**
   * workorder (karena FK tunggal `workorder.petugas_id`). Efeknya: laporan
   * "jumlah WO" meledak jadi N kali lipat, dan assignment tidak bisa
   * di-update sebagai satu kesatuan.
   *
   * Sekarang: **1 row** workorder + **N row** pivot + **1 row**
   * workorder_action (PENUGASAN) + progres awal (Mulai & Selesai).
   *
   * Return value tetap array supaya shape response tidak berubah untuk FE
   * Flutter — FE hanya melihat koleksi WO yang baru dibuat, dan sekarang
   * koleksi itu hanya punya 1 elemen (bukan N).
   */
  public function createWorkorders(array $data)
  {
    return DB::transaction(function () use ($data) {
      // Pastikan master action "PENUGASAN" selalu ada. Jika seeder sempat
      // gagal di tengah jalan, m_action bisa kosong sehingga insert
      // workorder_action akan lempar FK violation dan transaksi ini rollback
      // (gejala: WO seolah "berhasil" di FE tapi list tetap kosong).
      $penugasanActionId = $this->ensureDefaultActionExists();

      $lemburSplId = null;
      $statusId    = 2;

      if ((int) $data['tipe_workorder_id'] === 2) {
        $lemburSpl = LemburSpl::create([
          'status_id'       => 1,
          'waktu_pengajuan' => now(),
        ]);
        $lemburSplId = $lemburSpl->id;
        $statusId    = 1;
      }

      $workorder = Workorder::create([
        'judul_pekerjaan'    => $data['judul_pekerjaan'],
        'waktu_penugasan'    => $data['waktu_penugasan'],
        'estimasi_durasi'    => $data['estimasi_durasi'],
        'unit_waktu'         => $data['unit_waktu'],
        'estimasi_selesai'   => $data['estimasi_selesai'],
        'longitude'          => $data['longitude'] ?? null,
        'latitude'           => $data['latitude'] ?? null,
        'location_id'        => $data['location_id'] ?? null,
        'pic_id'             => $data['pic_id'],
        'lembur_spl_id'      => $lemburSplId,
        'status_id'          => $statusId,
        'jenis_workorder_id' => $data['jenis_workorder_id'],
        'jenis_lokasi_id'    => $data['jenis_lokasi_id'],
        'tipe_workorder_id'  => $data['tipe_workorder_id'],
      ]);

      // Attach multi-petugas ke pivot `workorder_petugas`. Memakai
      // `syncWithoutDetaching` bukan `attach` supaya kalau — misalnya —
      // FE tidak sengaja kirim id duplikat, insert tidak gagal di unique
      // constraint. `array_unique` sebagai safety tambahan.
      $petugasIds = array_values(array_unique(array_map('intval', $data['petugas_id'] ?? [])));
      if (!empty($petugasIds)) {
        $workorder->petugasList()->syncWithoutDetaching($petugasIds);
      }

      if ($statusId === 2) {
        (new ProgressWorkorderService())->createInitialProgress($workorder->id);

        // TKT-06: pelaku aksi PENUGASAN = SPV yang membuat WO (pic_id).
        // Tidak memakai auth()->id() di sini karena service ini bisa juga
        // dijalankan dari konteks non-request (mis. job/artisan) — pic_id
        // sudah pasti valid dari validator WorkorderController::store.
        (new WorkorderActionService())->createAction([
          'workorder_id'     => $workorder->id,
          'action_id'        => $penugasanActionId,
          'actor_id'         => $data['pic_id'],
          'keterangan'       => 'Penugasan awal',
          'waktu_mulai'      => $data['waktu_penugasan'],
          'estimasi_selesai' => $data['estimasi_selesai'],
        ]);
      }

      // Kontrak lama: mengembalikan array of workorders. Setelah TKT-07
      // tinggal 1 elemen karena WO tidak lagi di-duplikasi per petugas.
      // Eager-load relasi utama supaya response langsung lengkap.
      return [
        $workorder->load(
          'petugasList',
          'pic',
          'status',
          'jenisWorkorder',
          'jenisLokasi',
          'tipeWorkorder',
          'lemburSpl'
        ),
      ];
    });
  }

  /**
   * Memastikan record master action "Penugasan" selalu tersedia dan
   * mengembalikan id-nya untuk dipakai saat membuat workorder_action awal.
   *
   * Identifikasi pakai kolom `kode` (slug stabil) — bukan id numerik —
   * supaya tetap aman jika urutan seeder berubah atau master data bertambah.
   */
  private function ensureDefaultActionExists(): int
  {
    $action = MasterAction::firstOrCreate(
      ['kode' => 'PENUGASAN'],
      ['nama' => 'Penugasan', 'keterangan' => 'Penugasan kepada pegawai']
    );

    return (int) $action->id;
  }
}
