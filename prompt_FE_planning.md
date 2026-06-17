# PROMPT — Perencanaan Penyesuaian FE Mobile (Flutter) terhadap Perubahan Backend

> Relay isi di bawah ini **apa adanya** ke agent FE (Gemini). Ini adalah prompt **PERENCANAAN** — agent diminta MENGHASILKAN RENCANA, **belum** mengubah kode.

---

## PERAN & TUJUAN

Kamu adalah agent yang menangani **frontend mobile Flutter** di repo `F:\Project_mobile_pdam` (`project_mobile_pdam`). Backend Laravel-nya baru saja diubah besar-besaran di branch `MergerManual`. Tugasmu pada sesi ini **hanya membuat RENCANA penyesuaian FE** — jangan menulis/mengubah kode dulu. Hasil akhir = dokumen rencana bertahap + daftar file yang harus disentuh + risiko.

## KONTEKS PENTING (kenapa ada perubahan)

Ada **dua backend Laravel** terpisah untuk app ini:

- **Backend SOURCE (lama/golden)** — alur mobile 100% jalan, tapi memakai **tabel lookup numerik** (`m_status`, `m_tipe_progress` sebagai FK) dan identitas berbasis **`users` (user_id)**.
- **Backend TARGET (yang sekarang dipakai, branch `MergerManual`)** — Web Next.js sudah jalan; memakai **ENUM string** dan identitas berbasis **`m_pegawai` (pegawai_id)**.

**FE Flutter saat ini dibangun untuk backend SOURCE** (lihat `lib/core/constants/work_order_constants.dart`: `TipeProgressId.mulai=1`, `ProgressStatusId.submitted=10`, dst — semua **ID numerik**, plus identitas `user_id`). Backend TARGET **tidak lagi** memakai skema itu. **Inilah akar semua mismatch yang harus kamu rencanakan perbaikannya.**

Spesifikasi kontrak backend TARGET sudah disalin ke root repo FE sebagai **`F:\Project_mobile_pdam\summary_changes_BE.md`**. **Baca file itu lebih dulu, menyeluruh** — itu sumber kebenaran untuk endpoint, payload, dan bentuk response.

## PERUBAHAN KONTRAK BACKEND (ringkas — detail di summary_changes_BE.md)

1. **`tipe_progress` kini ENUM string**, bukan ID numerik. Ejaan enum DB: `inpeksi` (perhatikan: **`inpeksi`, bukan `inspeksi`**), `mulai`, `progress`, `selesai`. (FE sekarang mengirim `tipe_progress_id` numerik + `tipe_progress=INSPEKSI`/`PROGRESS`/`SELESAI` huruf besar — perlu diselaraskan dengan apa yang benar-benar diterima controller BE; **verifikasi langsung** ke controller BE bila perlu, jangan berasumsi soal normalisasi case.)

2. **`workorder.status` kini ENUM string**: `Pending` / `Proses` / `Selesai` / `Tutup`. Pemetaan transisi: assign→`Proses`, accept→`Selesai`, tolak→`Tutup`, revisi tetap `Proses`. (FE sekarang pakai `WorkOrderStatusId` numerik — usang.)

3. **`progress_workorder` TIDAK lagi punya kolom `status_id`.** Status siklus review (pending/approved/rejected) sekarang dilacak via tabel **`progress_detail`** (endpoint `GET /v1/progress-detail?progress_workorder_id={id}`). Konsekuensinya: parsing `status_id`/`statusId` di FE akan selalu null. `ProgressStatusId` (draft/submitted/verified/...) **usang**.

4. **Identitas pindah dari `user` ke `pegawai`.** Progress dilacak per anggota lewat `submitted_by_pegawai_id` (bukan `submitted_by_user_id`). Assignment member pakai `pegawai_id` (bukan `user_id`). Jembatan: `users.pegawai_id → m_pegawai`.

5. **`assigned_to` pada workorder = `m_pegawai.id` SPV** (di-serialize sebagai object Pegawai), `created_by = users.id`.

6. **Laporan akhir auto-generate** saat SPV `accept` (baris `laporan_workorder` muncul; ada `nomor_laporan`, `petugas_snapshot`, `hasil_akhir_snapshot`). Model & datasource laporan sudah ada di FE (`laporan_workorder_model.dart`, `laporan_workorder_remote.dart`) — verifikasi field-nya cocok.

7. **Aturan alur:** INSPEKSI **wajib kirim foto** (multipart `foto[]`); **SELESAI hanya boleh PIC** (anggota dgn `peran:"koordinator"` saat assign → `is_pic=true`); `start` gagal bila belum ada inspeksi; cancel hanya ≤5 menit & sebelum direview.

Daftar endpoint mobile lengkap (semua butuh `Authorization: Bearer`) ada di tabel pada `summary_changes_BE.md §2`. Payload assign-staff & alur uji end-to-end ada di §3–§4.

## MISMATCH YANG SUDAH TERKONFIRMASI (titik berisiko — verifikasi & masukkan ke rencana)

- **assign-staff** — `lib/feature/work_order/data/data_source/remote/work_order_remote_data_source.dart` (~baris 215–237) mengirim `petugas: [{ "user_id": ..., "peran": ... }]`. Backend TARGET mengharapkan **`pegawai_id`**, bukan `user_id`. **Breaking.**
- **constants** — `lib/core/constants/work_order_constants.dart`: `TipeProgressId` (ID numerik), `ProgressStatusId`, `WorkOrderStatusId` mencerminkan skema lookup lama. Perlu strategi: ganti ke enum string / peta baru.
- **model progress** — `lib/feature/work_order/data/models/work_order_progress_model.dart`: `fromMap` membaca `tipe_progress_id`, `status_id`, `submitted_by_user_id`. Tiga-tiganya berubah/hilang di TARGET. `toMap`/datasource mengirim `tipe_progress_id` + kode huruf besar.
- **datasource progress** — `work_order_progress_remote_data_source.dart`: logika `isStart/isSubmit` bergantung pada `tipeProgressId` numerik (1/2/3/6) dan mengirim `tipe_progress_id`, `tipe_progress_kode`, `tipe_progress` (UPPERCASE). Perlu diselaraskan ke enum target.
- **Identitas pegawai vs user** tersebar luas: grep awal menemukan **~317 kemunculan** pola `user_id/status_id/tipeProgressId/pegawai` di **58 file** (mayoritas di `lib/feature/work_order`). Termasuk: `member_progress_model.dart`, `progress_by_member_model.dart`, `assignees_model.dart`, `employee_model.dart`, `user_model.dart`, entitas terkait, dan halaman presentation (landing staff/spv, approval, detail WO, report). **Petakan mana yang benar-benar terdampak vs sekadar memuat string yang sama.**

## YANG HARUS KAMU LAKUKAN (sesi perencanaan ini)

1. Baca `summary_changes_BE.md` di root FE secara menyeluruh.
2. Telusuri kode FE (read-only) untuk memvalidasi setiap mismatch di atas dan menemukan yang belum terdaftar. Fokus berurutan: `core/constants` → `data/models` + `domain/entities` → `data/data_source/remote` → `data/repositories` + `domain/usecases` → `presentation/bloc` → `presentation/pages`.
3. Bedakan **breaking** (request gagal / parsing null / fitur mati) vs **kosmetik** (label, ID usang yang tidak dipakai).
4. **Jangan berasumsi** soal normalisasi/aliasing di BE — bila ragu apakah controller menerima `INSPEKSI` vs `inpeksi`, `user_id` vs `pegawai_id`, dst, catat sebagai **"perlu verifikasi ke controller BE"** dengan path file BE yang relevan (repo BE: `f:\backend-work-order`, controller di `app/Http/Controllers/`).

## DELIVERABLE (format keluaran yang diharapkan)

Hasilkan dokumen rencana berisi:

1. **Ringkasan dampak** — daftar perubahan kontrak BE → konsekuensi di FE (1 baris masing-masing).
2. **Rencana bertahap (fase)** dengan urutan dependensi yang benar: constants → model/entity → datasource → repository/usecase → bloc → UI. Tiap fase: tujuan, file yang disentuh (path lengkap), perubahan inti, cara verifikasi.
3. **Tabel file terdampak**: path | layer | jenis perubahan | breaking? (Y/T) | catatan.
4. **Daftar item "perlu verifikasi ke BE"** sebelum implementasi.
5. **Risiko & keputusan terbuka** yang butuh konfirmasi manusia (mis. apakah `TipeProgressId` numerik dipakai sebagai key UI di banyak widget sehingga lebih aman dipertahankan sebagai lapisan adaptor ketimbang dihapus).
6. **Strategi pengujian** mengikuti alur end-to-end di `summary_changes_BE.md §4` (akun uji: `spv@wo.test`/`senior@wo.test`/`staff@wo.test`, password `password`).

## BATASAN

- **Bahasa UI & pesan error: Bahasa Indonesia** (konvensi repo, lihat `CLAUDE.md`).
- Patuhi **Clean Architecture** repo (data/domain/presentation; DI via `get_it` di `lib/service/service_locator.dart`; state via `flutter_bloc`; networking via `RemoteDatasource` + `DataState<T>`).
- Endpoint diawali `/v1/`. Response paginated: `{ data, totalPages, currentPage }`. Error Laravel: `{ message, errors }`.
- Setelah ubah model/retrofit nanti (fase implementasi, **bukan sekarang**): jalankan `dart run build_runner build --delete-conflicting-outputs`, lalu `flutter analyze`.
- **Sesi ini: rencana saja, jangan edit kode.**
