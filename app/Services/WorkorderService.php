<?php

namespace App\Services;

use App\Models\MasterAction;
use App\Models\Status;
use App\Models\Workorder;
use Illuminate\Support\Facades\DB;

class WorkorderService
{
  /**
   * Membuat 1 workorder. Petugas (staff) akan di-assign oleh SPV nanti
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

      $statusId    = $this->statusIdByKode('DITUGASKAN_KE_SPV')
        ?? $this->statusIdByKode('DISETUJUI')
        ?? Status::query()->min('id');
      $assignedTo  = (int) ($data['assigned_to'] ?? $data['pic_id']);

      // Field timeline (estimasi_durasi, unit_waktu, estimasi_selesai,
      // tanggal_selesai) sudah dipindahkan ke workorder_assignment —
      // diisi oleh SPV saat assign staff, bukan oleh Superadmin saat create WO.
      $workorder = Workorder::create([
        'nama_workorder'     => $data['nama_workorder'] ?? $data['judul_pekerjaan'],
        'deskripsi'          => $data['deskripsi'] ?? null,
        'tanggal_mulai'      => $data['tanggal_mulai'] ?? $data['waktu_penugasan'],
        'tanggal_laporan'    => $data['tanggal_laporan'] ?? null,
        'lokasi'             => $data['lokasi'] ?? null,
        'assigned_to'        => $assignedTo,
        'created_by_user_id' => $data['created_by_user_id'] ?? null,
        'lembur_spl_id'      => $data['lembur_spl_id'] ?? null,
        'status_id'          => $statusId,
        'jenis_workorder_id' => $data['jenis_workorder_id'],
        'departemen_id'      => $data['departemen_id'] ?? null,
        'pengaduan_id'       => $data['pengaduan_id'] ?? null,
        'kpi_id'             => $data['kpi_id'] ?? null,
        'prioritas'          => $data['prioritas'] ?? null,
      ]);

      (new WorkorderActionService())->createAction([
        'workorder_id'     => $workorder->id,
        'action_id'        => $penugasanActionId,
        'actor_id'         => $data['created_by_user_id'] ?? $assignedTo,
        'keterangan'       => 'Superadmin membuat WO dan menugaskan SPV',
        'waktu_mulai'      => $data['tanggal_mulai'] ?? $data['waktu_penugasan'],
      ]);

      // Kontrak lama: mengembalikan array of workorders. Setelah TKT-07
      // tinggal 1 elemen karena WO tidak lagi di-duplikasi per petugas.
      // Eager-load relasi utama supaya response langsung lengkap.
      return [
        $workorder->load(
          'assignmentMembers',
          'pic',
          'status',
          'jenisWorkorder',
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
