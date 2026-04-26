<?php

namespace App\Services;

use App\Models\LemburSpl;
use App\Models\MasterAction;
use App\Models\Status;
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
      $statusId    = $this->statusIdByKode('DITUGASKAN_KE_SPV')
        ?? $this->statusIdByKode('DISETUJUI')
        ?? Status::query()->min('id');
      $assignedTo  = (int) ($data['assigned_to'] ?? $data['pic_id']);

      if ((int) $data['tipe_workorder_id'] === 2) {
        $lemburSpl = LemburSpl::create([
          'status_id'       => $statusId,
          'waktu_pengajuan' => now(),
        ]);
        $lemburSplId = $lemburSpl->id;
      }

      $workorder = Workorder::create([
        'nama_workorder'     => $data['nama_workorder'] ?? $data['judul_pekerjaan'],
        'deskripsi'          => $data['deskripsi'] ?? null,
        'tanggal_mulai'      => $data['tanggal_mulai'] ?? $data['waktu_penugasan'],
        'tanggal_laporan'    => $data['tanggal_laporan'] ?? null,
        'estimasi_durasi'    => $data['estimasi_durasi'],
        'unit_waktu'         => $data['unit_waktu'],
        'estimasi_selesai'   => $data['estimasi_selesai'],
        'tanggal_selesai'    => $data['tanggal_selesai'] ?? null,
        'lokasi'             => $data['lokasi'] ?? null,
        'longitude'          => $data['longitude'] ?? null,
        'latitude'           => $data['latitude'] ?? null,
        'location_id'        => $data['location_id'] ?? null,
        'assigned_to'        => $assignedTo,
        'created_by_user_id' => $data['created_by_user_id'] ?? null,
        'lembur_spl_id'      => $lemburSplId,
        'status_id'          => $statusId,
        'jenis_workorder_id' => $data['jenis_workorder_id'],
        'jenis_lokasi_id'    => $data['jenis_lokasi_id'],
        'tipe_workorder_id'  => $data['tipe_workorder_id'],
        'departemen_id'      => $data['departemen_id'] ?? null,
        'pengaduan_id'       => $data['pengaduan_id'] ?? null,
        'kpi_id'             => $data['kpi_id'] ?? null,
        'prioritas'          => $data['prioritas'] ?? null,
      ]);

      // Attach multi-petugas ke pivot `workorder_petugas`. Memakai
      // `syncWithoutDetaching` bukan `attach` supaya kalau — misalnya —
      // FE tidak sengaja kirim id duplikat, insert tidak gagal di unique
      // constraint. `array_unique` sebagai safety tambahan.
      $petugasIds = array_values(array_unique(array_map('intval', $data['petugas_id'] ?? [])));
      if (!empty($petugasIds)) {
        $workorder->petugasList()->syncWithoutDetaching($petugasIds);
      }

      // Flow baru: saat create WO oleh Superadmin, WO masih pada tahap
      // DITUGASKAN_KE_SPV. Progress awal belum di-spawn dan assignment staff
      // belum wajib terjadi di tahap ini.
      (new WorkorderActionService())->createAction([
        'workorder_id'     => $workorder->id,
        'action_id'        => $penugasanActionId,
        'actor_id'         => $data['created_by_user_id'] ?? $assignedTo,
        'keterangan'       => 'Superadmin membuat WO dan menugaskan SPV',
        'waktu_mulai'      => $data['tanggal_mulai'] ?? $data['waktu_penugasan'],
        'estimasi_selesai' => $data['estimasi_selesai'],
      ]);

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

  private function statusIdByKode(string $kode): ?int
  {
    return Status::where('kode', $kode)->value('id');
  }
}
