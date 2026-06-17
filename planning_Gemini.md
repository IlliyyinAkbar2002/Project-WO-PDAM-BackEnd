# Perencanaan Penyesuaian FE Mobile (Flutter) terhadap Perubahan Backend

Adaptasi frontend mobile Flutter PDAM agar selaras dengan perubahan kontrak backend Laravel pada branch `MergerManual`. Sesi ini berfokus pada analisis, pemetaan mismatch, dan penyusunan rencana implementasi bertahap tanpa mengubah kode sumber.

## Ringkasan Dampak

Berikut adalah daftar perubahan kontrak backend (BE) beserta konsekuensinya pada frontend (FE):

| Perubahan Kontrak Backend (BE) | Konsekuensi di Frontend (FE) |
|---|---|
| Status WO menggunakan ENUM string (`Pending`, `Proses`, `Selesai`, `Tutup`) | FE harus mengonversi dari/ke status ID numerik (`12`, `13`, `6`, `17`) agar tidak merombak widget UI. |
| Tipe progress menggunakan ENUM string (`inpeksi`, `mulai`, `progress`, `selesai`) | Model progress harus memetakan string enum ke konstanta ID numerik (`6`, `1`, `2`, `3`). |
| Kolom `status_id` dihapus dari tabel `progress_workorder` | Status review progress diturunkan secara dinamis dari detail review terakhir pada relation `latestDetail` / `progressDetails`. |
| Alur review dipindah dari `detail-progress` ke `progress-detail` (read-only) | Endpoint `/v1/detail-progress` dialihkan ke `/v1/progress-detail`. Aksi review dilakukan via `/v1/progress-workorder/review`. |
| Pengiriman petugas pada `assign-staff` menggunakan `pegawai_id` | Payload request key `user_id` diubah menjadi `pegawai_id` pada list `petugas`. |
| Relasi anggota tim (`assignmentMembers.user`) mengembalikan model `Pegawai` | `UserModel` harus bisa mem-parsing data flat `Pegawai` jika field nested `pegawai` kosong. |
| Laporan akhir di-generate otomatis oleh BE saat SPV menyetujui WO | Tombol submit laporan pada halaman `laporan_workorder.dart` dinonaktifkan (hanya view/PDF export). |

---

## User Review Required

> [!IMPORTANT]
> **Strategi Adaptor Model (Rekomendasi Utama)**:  
> Untuk meminimalkan risiko regresi, kita akan **mempertahankan** konstanta ID numerik (`TipeProgressId`, `ProgressStatusId`, `WorkOrderStatusId`) di tingkat domain dan UI Flutter. Pemetaan dari string enum BE ke ID numerik FE dilakukan secara internal di dalam method `fromMap` milik model, sedangkan pemetaan dari ID numerik FE ke string enum BE dilakukan di remote data source sebelum payload dikirim. Strategi ini menghindari perubahan pada 50+ file UI widget.

> [!WARNING]
> **Ejaan `inpeksi` di DB**:  
> Kolom tipe progress di DB menggunakan ejaan `'inpeksi'` (tanpa huruf 's'). Backend controller otomatis mengonversi string request `'INSPEKSI'` menjadi `'inpeksi'` via mapper internal. FE akan tetap mengirim string `'INSPEKSI'` agar konsisten secara penulisan kode, namun model parser FE harus toleran terhadap `'inpeksi'` dan `'inspeksi'`.

---

## Open Questions

> [!NOTE]
> **Apakah ada validasi sisa waktu pembatalan di sisi FE?**  
> BE membatasi pembatalan progress maksimal 5 menit setelah submit. Di FE, `WorkOrderProgressEntity` memiliki getter `canCancel` berbasis selisih waktu lokal. Kita perlu memastikan sinkronisasi waktu server vs lokal agar tidak memicu false-positive tombol batal di UI.

---

## Proposed Changes

### 1. Core & Constants

#### [MODIFY] [work_order_constants.dart](file:///f:/Project_mobile_pdam/lib/core/constants/work_order_constants.dart)
* Tidak ada perubahan kode pada file ini, konstanta `TipeProgressId`, `ProgressStatusId`, dan `WorkOrderStatusId` dipertahankan sebagai referensi pemetaan di layer model.

---

### 2. Data Models & Entities

#### [MODIFY] [work_order_progress_model.dart](file:///f:/Project_mobile_pdam/lib/feature/work_order/data/models/work_order_progress_model.dart)
* Ubah `fromMap` agar mendukung mapping tipe progress baru:
  * `'inpeksi'` / `'inspeksi'` &rarr; `TipeProgressId.inspeksi` (6)
  * `'mulai'` &rarr; `TipeProgressId.mulai` (1)
  * `'progress'` &rarr; `TipeProgressId.progress` (2)
  * `'selesai'` &rarr; `TipeProgressId.selesai` (3)
* Ubah `fromMap` agar mendeteksi status progress secara dinamis dari `progress_details` atau `latest_detail` (menggantikan `detail_progress`):
  * Jika detail status `'approved'` &rarr; `ProgressStatusId.verified` (11)
  * Jika detail status `'rejected'` dengan `field_to_revise` &rarr; `ProgressStatusId.revisiRequested` (14)
  * Jika detail status `'pending'` &rarr; `ProgressStatusId.submitted` (10)
  * Jika `waktu_submit` null &rarr; `ProgressStatusId.dibatalkan` (18)
* Ubah `fromMap` agar membaca `submitted_by_pegawai_id` sebagai fallback ke field `submittedByUserId`.
* Ubah `fromMap` agar toleran membaca data review detail dari field `'progress_details'` dan `'latest_detail'`.

#### [MODIFY] [user_model.dart](file:///f:/Project_mobile_pdam/lib/feature/work_order/data/models/user_model.dart)
* Ubah `fromMap` agar toleran jika parameter `map` adalah flat `Pegawai` (alias `member['user']` dari relation `WoAssignmentMember.user` di BE):
  * Jika `map['pegawai']` null tapi terdapat field `nama` atau `name` secara langsung, maka map tersebut di-parse secara penuh sebagai `employee`.
  * Set `employeeId` dari `map['id']`.

#### [MODIFY] [member_progress_model.dart](file:///f:/Project_mobile_pdam/lib/feature/work_order/data/models/member_progress_model.dart)
* Ubah `fromMap` agar memetakan `userId` dari `map['pegawai_id']` jika `map['user_id']` bernilai null.

#### [MODIFY] [work_order_model.dart](file:///f:/Project_mobile_pdam/lib/feature/work_order/data/models/work_order_model.dart)
* Ubah `fromMap` untuk memetakan string status WO menjadi `statusId` numerik:
  * `'Pending'` &rarr; `WorkOrderStatusId.ditugaskanKeSpv` (12)
  * `'Proses'` &rarr; `WorkOrderStatusId.ditugaskanKeStaff` (13)
  * `'Selesai'` &rarr; `WorkOrderStatusId.selesai` (6)
  * `'Tutup'` &rarr; `WorkOrderStatusId.ditolakManager` (17)

---

### 3. Remote Data Sources

#### [MODIFY] [work_order_remote_data_source.dart](file:///f:/Project_mobile_pdam/lib/feature/work_order/data/data_source/remote/work_order_remote_data_source.dart)
* Di dalam method `assignStaff()`, ubah nama field key pengiriman petugas dari `'user_id'` menjadi `'pegawai_id'`:
  ```dart
  // Sebelum: 'user_id': entry.value
  // Sesudah: 'pegawai_id': entry.value
  ```
* Di dalam method `fetchWorkOrders()`, petakan parameter query `status` (list integer) menjadi string status tunggal yang dipahami BE:
  * Jika berisi `12` &rarr; `'Pending'`
  * Jika berisi `13`, `7`, atau `5` &rarr; `'Proses'`
  * Jika berisi `6` &rarr; `'Selesai'`
  * Jika berisi `17` &rarr; `'Tutup'`

#### [MODIFY] [progress_detail_remote_data_source.dart](file:///f:/Project_mobile_pdam/lib/feature/work_order/data/data_source/remote/progress_detail_remote_data_source.dart)
* Ubah endpoint paths dari `/v1/detail-progress` menjadi `/v1/progress-detail`.
* Tandai method `updateProgressDetail` sebagai deprecated karena perubahan status review sekarang ditangani melalui endpoint review progress (`POST /v1/progress-workorder/review`).

---

## Verification Plan

### Manual Verification Flow (End-to-End)

Pengujian akan dilakukan menggunakan akun uji di database local:
* **SPV**: `spv@wo.test` (password: `password`)
* **PIC / Koordinator**: `senior@wo.test` (password: `password`)
* **Staff Anggota**: `staff@wo.test` (password: `password`)

```mermaid
sequenceAxis
    SPV ->> Mobile: Assign Staff & Kategori Form
    Note over Mobile, BE: Status WO berubah jadi 'Proses'
    Staff ->> Mobile: Submit Inspeksi (+ Foto Wajib)
    Staff ->> Mobile: Start Work order
    Staff ->> Mobile: Submit Progress
    PIC ->> Mobile: Submit Selesai
    Note over Mobile, BE: Status detail_progress menjadi 'pending'
    SPV ->> Mobile: Review & Decision (Revisi / Accept)
    Note over Mobile, BE: Jika Accept, Laporan auto-generate & WO 'Selesai'
```

1. **Fase 1: SPV Assign Staff**
   * Login sebagai SPV.
   * Pilih WO berstatus "Pending" (ID: 12).
   * Lakukan assign staff (`senior@wo.test` sebagai PIC/koordinator, `staff@wo.test` sebagai anggota).
   * Verifikasi: WO berhasil berpindah status ke "Proses" di halaman utama.

2. **Fase 2: Submit Inspeksi & Mulai Kerja**
   * Login sebagai Staff.
   * Pilih WO yang tadi di-assign.
   * Ambil foto (wajib) dan submit progress "INSPEKSI".
   * Klik "Mulai" untuk mengaktifkan WO.
   * Verifikasi: Status tahapan pekerjaan berpindah ke persiapan.

3. **Fase 3: Submit Progress & Selesai**
   * Kirim beberapa progress pengerjaan.
   * Login sebagai Koordinator (PIC) untuk mengirim progress "SELESAI".
   * Isi kategori form akhir (mis. kondisi meter akhir, tindakan perbaikan, dll).
   * Verifikasi: Laporan terkirim ke SPV dan status review menjadi pending.

4. **Fase 4: Review & Auto-Generate Laporan**
   * Login kembali sebagai SPV.
   * Buka menu review progress.
   * Berikan keputusan "Accept" dengan catatan review.
   * Verifikasi: Status WO berubah menjadi "Selesai" (100% progres), dan data Laporan berhasil di-load di halaman laporan/PDF.
