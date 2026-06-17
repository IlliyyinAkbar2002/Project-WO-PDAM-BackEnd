# Summary Perubahan Backend — Alur Mobile (Assignment → Progress → Review → Laporan)

Branch: `MergerManual`. Tujuan: melengkapi alur Flutter Mobile (assignment → progress → submit → review SPV → auto-generate Laporan) dengan mengadaptasi kode dari backend source ke **skema ENUM + pegawai** milik project ini. Route/kontrak Web Next.js TIDAK diubah.

> Konteks skema: `workorder.status` = ENUM (`Pending/Proses/Selesai/Tutup`); `workorder.assigned_to` = `m_pegawai` (SPV); `progress_workorder.tipe_progress` = ENUM (`inpeksi/mulai/progress/selesai`), tanpa `status_id`. Identitas user↔pegawai dijembatani `users.pegawai_id`. Status siklus review dilacak via tabel `progress_detail`.

---

## 1. File yang dibuat / diubah

**Migrasi**
- (baru) `2026_06_17_200000_create_laporan_workorder_table.php` — tabel `laporan_workorder` (modelnya sudah ada, tabel belum).
- (baru) `2026_06_17_201000_add_submitted_by_to_progress_workorder_table.php` — kolom `submitted_by_pegawai_id` (FK `m_pegawai`) untuk pelacakan progress per anggota.
- (fix) `2026_05_09_150000_add_location_columns_to_progress_workorder.php` — `->after('field_to_revise')` → `->after('waktu_submit')` (kolomnya tak ada; fatal di MySQL, no-op di Postgres).
- (fix) `2026_06_17_192839_create_progress_detail.php` — nama class `CreateProgressDetailTable` → `CreateProgressDetail` agar cocok dengan filename (Laravel 8 me-`require` ulang file kalau class ≠ studly(filename) → "Cannot redeclare").

**Model**
- (baru) `app/Models/ProgressDetail.php` — `progressWorkorder()`, `reviewer()` (User + pegawai).
- (ubah) `app/Models/ProgressWorkorder.php` — buang relasi `DetailProgress` yang terhapus; tambah `progressDetails()`, `latestDetail()`, `submitter()` (Pegawai).
- (ubah) `app/Models/Workorder.php` — tambah `progressWorkorder()` (hasMany) & `laporanWorkorder()` (hasOne).
- (ubah) `app/Models/WoAssignmentMember.php` — tambah relasi `pegawai()` (alias `user()` dipertahankan).

**Service**
- (tulis ulang) `app/Services/ProgressWorkorderService.php` — buang `Status`/`TipeProgress`; pakai enum.
- (fix) `app/Services/AssignmentService.php` — fallback kategori ke kolom `jenis_workorder.kategori` (sebelumnya baca `kategori_form` yang tidak ada → assignment gagal).

**Controller**
- (tulis ulang) `app/Http/Controllers/ProgressWorkorderController.php` — adaptasi penuh ke enum + pegawai; review-state via `progress_detail`; Laporan auto-generate saat approve.
- (baru) `app/Http/Controllers/ProgressDetailController.php` — riwayat review (read-only).
- (fix) `app/Http/Controllers/LaporanWorkorderController.php` — validasi `exists:workorders,id` → `exists:workorder,id`.
- (fix) `app/Http/Controllers/AssignmentWorkorder.php` — fallback kategori ke `kategori`.

**Routes** (`routes/api.php`) — hapus import controller terhapus (`JenisLokasiController`, `DetailProgressController`); ganti `apiResource('progress-workorder')` & `detail-progress` jadi route eksplisit; tambah `progress-detail` & `laporan-workorder`. Route Web Next.js tetap.

---

## 2. Endpoint Mobile (semua butuh header `Authorization: Bearer <token>`)

| Method | Path | Fungsi | Pemanggil |
|---|---|---|---|
| GET  | `/api/v1/workorder/{id}/assignment` | Lihat detail assignment | SPV |
| POST | `/api/v1/workorder/{id}/assign-staff` | SPV menugaskan staff | SPV |
| POST | `/api/v1/progress-workorder/submit` | Submit INSPEKSI / PROGRESS / SELESAI | Staff |
| POST | `/api/v1/progress-workorder/start` | Mulai kerja (MULAI) | Staff |
| POST | `/api/v1/progress-workorder/resubmit` | Kirim ulang setelah revisi | PIC/Staff |
| POST | `/api/v1/progress-workorder/review` | Review: accept / revisi / tolak | SPV |
| POST | `/api/v1/progress-workorder/{id}/cancel` | Batal (≤5 menit, sblm direview) | Staff |
| GET  | `/api/v1/progress-workorder/by-member/{workorderId}` | Progress per anggota | SPV |
| GET  | `/api/v1/progress-workorder/member-summary/{workorderId}` | Ringkasan per anggota | SPV/Web |
| GET  | `/api/v1/progress-workorder?workorder_id={id}` | List progress | Staff/SPV |
| GET  | `/api/v1/progress-workorder/{id}` | Detail progress + kategori_data | Staff/SPV |
| GET  | `/api/v1/progress-detail?progress_workorder_id={id}` | Riwayat review | SPV |
| GET  | `/api/v1/laporan-workorder` , `/{id}` | Lihat laporan akhir | SPV/Web |

---

## 3. Cara membuat Work Order (ditujukan ke SPV) via API

Endpoint **tidak berubah** (milik Web Next.js): `POST /api/v1/workorder`.

```
POST /api/v1/workorder
Authorization: Bearer <token-superadmin>
Content-Type: application/json
Accept: application/json

{
  "nama_workorder": "Pembersihan Saluran",
  "deskripsi": "Bersihkan saluran tersumbat",
  "lokasi": "otomatis ditimpa dari pengaduan",
  "prioritas": "Sedang",
  "status": "Pending",
  "kode_pengaduan": "PGD-001",
  "departemen_id": 1,
  "jenis_workorder_id": 6,
  "assigned_to": 14,
  "created_by": 1
}
```

Catatan penting:
- **`assigned_to` = `m_pegawai.id` milik SPV** (mis. `14` pada contohmu) — inilah "ditujukan ke SPV".
- **`created_by` = `users.id`** pembuat (superadmin, mis. `1`).
- `kode_pengaduan` wajib ada di tabel `pengaduan`.
- **`lokasi` WAJIB ada di body** (`required`) walau nilainya ditimpa otomatis dari `pengaduan.lokasi` — kalau tidak dikirim → `ValidationException` (muncul sebagai 500 "The given data was invalid." karena Handler me-render semua exception jadi 500).
- **`status` dipaksa `Pending`** oleh server, apa pun yang dikirim.
- `jenis_workorder_id` harus `is_active = true`.
- Response: object `data` (assigned_to = object Pegawai, created_by = object User, + `departemen`, `jenis_workorder`) — sama persis dengan bentuk show yang kamu pakai.

Contoh curl:
```bash
curl -X POST http://localhost:8000/api/v1/workorder \
  -H "Authorization: Bearer <TOKEN>" \
  -H "Content-Type: application/json" -H "Accept: application/json" \
  -d '{"nama_workorder":"Pembersihan Saluran","lokasi":"-","prioritas":"Sedang","status":"Pending","kode_pengaduan":"PGD-001","departemen_id":1,"jenis_workorder_id":6,"assigned_to":14,"created_by":1}'
```

> Token didapat dari `POST /api/v1/auth/login` (kredensial superadmin) → ambil token dari response.

---

## 4. Urutan uji alur Mobile (end-to-end)

Prasyarat data: user SPV dengan `users.pegawai_id == workorder.assigned_to`; user staff dengan `pegawai_id` = pegawai yang di-assign. **Disediakan oleh `StaffTestSeeder`**: `spv@wo.test`, `senior@wo.test` (PIC), `staff@wo.test` — semua password `password`. Lihat `pegawai_id` di output console saat seeding.

1. **SPV assign staff** — `POST /workorder/{id}/assign-staff` (token SPV). Status WO → `Proses`.
   ```json
   {
     "petugas": [
       {"pegawai_id": 20, "peran": "koordinator"},
       {"pegawai_id": 21, "peran": "anggota"}
     ],
     "form_kategori": {"jenis_pipa": "PVC", "diameter_pipa": 4, "panjang_pipa": 100, "tingkat_kerusakan": "Sedang"},
     "latitude": -7.29, "longitude": 112.73, "accuracy": 5,
     "tanggal_mulai": "2026-06-18 08:00:00", "estimasi_selesai": "2026-06-20 17:00:00"
   }
   ```
   (`kategori` otomatis dari `jenis_workorder.kategori`; `peran:"koordinator"` → `is_pic=true`. Untuk `jaringan`, `form_kategori.jenis_pipa` wajib.)

2. **Staff INSPEKSI** — `POST /progress-workorder/submit` (multipart, **foto wajib**): `workorder_id`, `tipe_progress=INSPEKSI`, `hasil_pengerjaan`, `latitude`, `longitude`, `foto[]`.

3. **Staff MULAI** — `POST /progress-workorder/start`: `{ "workorder_id":1, "latitude":-7.29, "longitude":112.73 }` (gagal kalau belum ada inspeksi).

4. **Staff PROGRESS** — `POST /progress-workorder/submit`: `{ "workorder_id":1, "tipe_progress":"PROGRESS", "hasil_pengerjaan":"50%", "latitude":..,"longitude":.., "tahapan":2 }`.

5. **PIC SELESAI** — `POST /progress-workorder/submit` (hanya `is_pic`): `{ "workorder_id":1, "tipe_progress":"SELESAI", "hasil_pengerjaan":"selesai", "latitude":..,"longitude":.., "tindakan_perbaikan":"ganti pipa", "hasil_inspeksi":"normal" }` → buat `progress_detail` pending + notif SPV.

6. **SPV revisi** — `POST /progress-workorder/review`: `{ "progress_id": <id_selesai>, "decision":"revisi", "alasan_penolakan":"foto kurang", "field_to_revise":["foto"] }`.

7. **PIC resubmit** — `POST /progress-workorder/resubmit`: `{ "progress_id": <id>, "hasil_pengerjaan":"perbaikan", "latitude":..,"longitude":.., "tindakan_perbaikan":"...", "hasil_inspeksi":"..." }`.

8. **SPV accept** — `POST /progress-workorder/review`: `{ "progress_id": <id>, "decision":"accept", "approval_notes":"OK" }` → **WO `Selesai` + baris `laporan_workorder` ter-generate**.

9. **Verifikasi** — `GET /laporan-workorder` (cek `nomor_laporan`, `petugas_snapshot`, `hasil_akhir_snapshot`) & `GET /progress-workorder/by-member/1`.

---

## 5. Catatan & risiko untuk testing FE

- **Bentuk response berubah** dari source: progress pakai `pegawai_id` (bukan `user_id`), `status` berupa enum string. FE Mobile mungkin perlu sedikit penyesuaian field.
- **SELESAI hanya boleh PIC** (`peran:"koordinator"` saat assign). Cek `SENIOR_STAFF` dihilangkan karena `m_jabatan` tak punya kolom `kode`.
- **INSPEKSI wajib mengirim foto** (multipart, `foto[]`).
- Pemetaan status WO: assign→`Proses`, accept→`Selesai`, tolak→`Tutup`, revisi tetap `Proses`.
- `tipe_progress` mengikuti ejaan enum DB: `inpeksi` (bukan `inspeksi`), `mulai`, `progress`, `selesai`.

## 6. Migrasi & langkah jalan
```
php artisan migrate:fresh --seed   # DatabaseSeeder kini juga memanggil PengaduanSeeder + StaffTestSeeder
php artisan route:list             # verifikasi route
```
`--seed` menghasilkan: superadmin (`superadmin@gmail.com`/`password`), pengaduan `PGD-001..010`, dan akun uji `spv@wo.test` / `senior@wo.test` / `staff@wo.test` (password `password`). Untuk hanya akun uji: `php artisan db:seed --class="Database\Seeders\StaffTestSeeder"`.

## 7. Fase lanjutan (belum dikerjakan)
- Progress Lembur (`ProgressLemburController` + `LemburApprovalService`).
- Peminjaman Material (`WoPeminjamanMaterialController` + `PeminjamanMaterialService`).
- Utang teknis pre-existing (di luar scope, perlu ditangani terpisah): `LemburSplController`/`LemburSpl` masih merujuk `App\Models\Status` + `m_status` (tak ada di target); `JenisWorkorderService` masih merujuk `FormWorkorder`/`DetailForm` yang terhapus.
