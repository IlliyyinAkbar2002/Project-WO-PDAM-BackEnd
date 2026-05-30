# MoSCoW Scope — Capstone Work Order Management PDAM

> **Tim:** 2 orang (Backend Laravel + FE Mobile Flutter = Nika, FE Web Next.js = Geo). Backend dikerjakan **bersama** (Skenario A) dengan disiplin branch ketat.
>
> **Tujuan dokumen:** mengunci cakupan fitur sebelum eksekusi — supaya keputusan "apa yang dikerjakan / apa yang TIDAK dikerjakan" bisa dipertanggungjawabkan di sidang TA. Setiap poin di `WON'T` adalah **pengecualian yang sengaja** (bukan "belum sempat").

---

## Ringkasan Keputusan Desain yang Sudah Disepakati

| No | Keputusan | Sumber |
|----|-----------|--------|
| 1 | Form WO pakai **template-driven schema** (`form_template` + `workorder.form_values JSONB`) — bukan EAV | Q1, Q2 |
| 2 | Multi-petugas per WO → pivot `workorder_petugas` | Q3 (sudah selesai TKT-07) |
| 3 | Dokumentasi foto disimpan di Cloudinary (fallback local) | Q4 |
| 4 | **Manager** sebagai role aktif: assign SPV + approve final | Q5, Q6 |
| 5 | Relasi `pengaduan ↔ workorder` = **1:N** (pengaduan_id FK nullable) | Q7 |
| 6 | Sumber pengaduan: **seeder mock** (API PDAM live tidak wajib) | Q10 |
| 7 | **2 departemen** (Operasional, Pelayanan) — bukan 3 | Q11 |
| 8 | Manager hanya assign WO di departemennya sendiri | Q11 |
| 9 | Penolakan / Revisi SPV = **append** (tidak hapus row) | Q12 |
| 10 | Staff Revisi hanya **melengkapi field yang diminta**, bukan submit ulang seluruh form | Q12 |
| 11 | Tambah table `laporan_workorder` (1:1 WO) dengan nomor laporan + snapshot | Q13 |
| 12 | Cetak PDF laporan **untuk keperluan internal saja** | Q14 |

---

## MUST HAVE — Core End-to-End Flow

> Kalau ini tidak jalan, TA goyang. **Prioritas nomor 1 untuk semua kategori ini.**

### M1. Autentikasi & Otorisasi
- [ ] Login via email + password (Sanctum token-based)
- [ ] 4 role: `Super Admin`, `Manager`, `SPV`, `Staff` *(catatan: saat ini SPV/Staff dibedakan lewat `jabatan_id`, bukan `role_id` — konfirmasi dengan Geo apakah ini dipertahankan)*
- [ ] Policy: Manager hanya bisa assign SPV **di departemennya sendiri**
- [ ] Logout + revoke token

### M2. Master Data (Web — dikerjakan Geo)
- [ ] CRUD **Jenis WO** + **Form Template per jenis WO** (Super Admin)
- [ ] CRUD **Pegawai** + assign ke departemen + role + jabatan
- [ ] CRUD **Jenis Pengaduan**
- [ ] Read-only: `m_status`, `m_tipe_progress`, `m_action`, `m_departemen` (2 row: Operasional, Pelayanan)

### M3. Manajemen Pengaduan (Web — Admin Bagian)
- [ ] List pengaduan + filter status/jenis
- [ ] Detail pengaduan (dengan info pelapor dari seeder mock)
- [ ] **Generate WO dari pengaduan** (link otomatis via `workorder.pengaduan_id`)

### M4. Create & Assign Workorder
- [ ] **Admin bikin WO** (web, form lengkap: judul, jenis WO, estimasi, lokasi, referensi pengaduan opsional)
- [ ] **Manager assign SPV** (web) — dengan validasi departemen
- [ ] **SPV assign multi-Staff** (mobile) — memilih 1 atau beberapa Staff dari tim-nya

### M5. Eksekusi WO oleh Staff (Mobile)
- [ ] List WO "ditugaskan ke saya"
- [ ] Detail WO + form per jenis WO (dirender dari `form_template`)
- [ ] Isi form `form_values` + upload foto (Cloudinary URL)
- [ ] Tombol **Mulai** (buat progres `MULAI`) + **Submit Selesai** (progres `SELESAI` + isi hasil)

### M6. Review SPV (Mobile)
- [ ] List WO "menunggu verifikasi saya"
- [ ] Detail hasil Staff (form + foto + narasi)
- [ ] 3 aksi dengan reason/catatan:
  - `Terima` → lanjut ke Manager
  - `Revisi` → Staff lengkapi field yang diminta (append progres `REVISI`)
  - `Tolak` → final rejection (append progres `DITOLAK`)

### M7. Approval Manager (Web)
- [ ] List WO "menunggu approval saya"
- [ ] Detail lengkap + tombol `Setujui` / `Tolak` (dengan `catatan_manager`)
- [ ] Trigger **auto-generate laporan WO** saat Setujui

### M8. Laporan Workorder
- [ ] Table `laporan_workorder` 1:1 dengan `workorder`
- [ ] Auto-generate saat Manager approve: nomor (`LAP-WO-YYYY-NNNN`), snapshot `form_values`, snapshot petugas, approval timestamps
- [ ] Halaman detail laporan (web + mobile)
- [ ] **Cetak PDF** (web, `barryvdh/laravel-dompdf`) → disimpan URL di `laporan_workorder.pdf_url`

### M9. Seeder Data Demo
- [ ] 2 Departemen (Operasional, Pelayanan)
- [ ] Multi-user untuk tiap role (1 Admin, 2 Manager, 4 SPV, 8 Staff)
- [ ] ~20 Pengaduan realistis (AIR_KERUH, KEBOCORAN, METER, dll)
- [ ] 5 Jenis WO + form_template untuk tiap jenis (3-5 field)
- [ ] 5-10 WO di berbagai status untuk demo

### M10. Dokumentasi Project
- [ ] README — cara setup + run + seed
- [ ] ERD Logical + Physical (disimpan di `/docs`)
- [ ] OpenAPI spec (di `.cursor/OpenAPI.md`)
- [ ] BPMN yang sudah sinkron dengan decisions

---

## SHOULD HAVE — Nilai Tambah, Boleh Minimalis

> Sangat disarankan ada untuk sidang, tapi boleh sederhana.

### S1. List & Filter
- [ ] List WO dengan filter: status, jenis WO, tanggal, petugas, SPV (web + mobile)
- [ ] Pagination + search by `judul_pekerjaan` / nomor WO
- [ ] List pengaduan dengan filter jenis & status

### S2. Dashboard Sederhana
- [ ] **Admin/Super Admin**: total WO per status, total pengaduan belum ditindak
- [ ] **Manager**: WO pending approval, WO departemennya per status
- [ ] **SPV**: WO saya yang menunggu verifikasi, WO tim saya in progress
- [ ] **Staff**: WO saya hari ini

### S3. Audit Trail
- [ ] `workorder_action` mencatat: `PENUGASAN`, `APPROVE`, `REJECT`, `REVISI` (minimal 4 action)
- [ ] Halaman "History WO" menampilkan timeline action + progres

### S4. Dokumentasi Foto
- [ ] Upload ke Cloudinary (jika setup berhasil)
- [ ] Fallback: simpan local `/storage/app/public/dokumentasi`
- [ ] Validasi: max 5 foto per progres, max 2MB per foto

### S5. Validasi & Error Handling
- [ ] Laravel `FormRequest` untuk semua endpoint WO-related
- [ ] `ValidationException` handler → HTTP 422 dengan body standar `{message, errors}` (TKT-08)
- [ ] Flutter & Next.js menampilkan error inline pada form

---

## COULD HAVE — Bonus Kalau Waktu Sisa

> Tidak wajib. Tidak dikerjakan kalau deadline mepet.

### C1. Real-time & Notifikasi
- [ ] Push notifikasi FCM ke Mobile (WO baru ditugaskan, hasil direvisi, dll)
- [ ] Web: polling status (tidak full real-time)

### C2. GPS & Location
- [ ] Presensi kehadiran Staff di lokasi WO (`user_locations`)
- [ ] Map view lokasi WO di mobile (Google Maps atau OSM)

### C3. Analytics
- [ ] Chart bar: jumlah WO selesai per bulan
- [ ] Chart pie: distribusi jenis WO
- [ ] Leaderboard Staff tercepat menyelesaikan WO

### C4. Export & Print Batch
- [ ] Export list WO ke Excel (`maatwebsite/excel`)
- [ ] Print banyak laporan sekaligus (PDF merge)

### C5. KPI & Penilaian Staff
- [ ] Kalkulasi KPI berdasarkan `m_kpi` (saat ini baru master kosong)

### C6. Freeze / Resume / Extend WO
- [ ] Aksi `FREEZE` (WO dipause sementara, misal menunggu material)
- [ ] Aksi `RESUME` (lanjutkan WO yang di-freeze)
- [ ] Aksi `EXTEND` (perpanjang estimasi selesai)

> Note: Infrastruktur `workorder_action` sudah ada — tinggal implementasi UI & validation. Tapi ini Could, bukan Must.

---

## WON'T HAVE (Scope Sidang Ini) — Tegaskan di Proposal

> Fitur yang **sengaja dieksklusi**. Kalau penguji tanya, jawab tegas: *"Sudah dieksklusi dari awal via MoSCoW analysis untuk menjaga fokus pada core flow WO."*

### W1. Integrasi Eksternal
- **Integrasi live API PDAM Perumda Surabaya** → diganti seeder mock (alasan: ketersediaan API tidak bisa dijamin untuk timeline TA)
- **WhatsApp / SMS notifikasi** ke pelanggan
- **Payment gateway** / integrasi keuangan (Keuangan tidak termasuk scope, itu alasan `Keuangan` didrop dari departemen)
- **Email notifikasi** (reset password manual oleh admin)

### W2. Fitur User-Facing
- **Portal pelanggan** (pelanggan lapor mandiri via web/app)
- **Rating/feedback** pelanggan terhadap hasil WO
- **Chat real-time** SPV ↔ Staff

### W3. Operational / Enterprise
- **Multi-tenant** (hanya 1 PDAM)
- **Role/permission granular** (RBAC kompleks — cukup 4 role fixed)
- **Audit log edit field-level** (cukup `workorder_action` level event)
- **Soft delete + restore UI** (cukup `SoftDeletes` di backend, restore via DB langsung)
- **Approval multi-level > 2** (stop di `SPV → Manager`)
- **Versioning dokumen laporan** (1 WO = 1 laporan final, tidak ada revisi laporan)

### W4. UI/UX Lanjut
- **Dark mode**
- **Multi-bahasa** (Bahasa Indonesia saja)
- **PWA offline mode** untuk Mobile
- **Drag-and-drop** di form builder (gunakan input form standar)

### W5. Testing Advanced
- **E2E testing** (Cypress / Playwright)
- **Load testing**
- Cukup: **unit test** untuk service layer kritikal (WorkorderService, ProgressWorkorderService, LaporanWorkorderService) — minimal 10 test case

---

## Estimasi Timeline (8 Minggu)

| Minggu | Fokus | PIC |
|--------|-------|-----|
| **1** | Finalisasi ERD Physical + schema migration + OpenAPI contract lock | Nika + Geo |
| **2** | Backend refactor: implementasi TKT-01 s/d TKT-07 (sudah merged) + TKT-08 s/d TKT-15 (baru). Seeder final. | Nika + Geo (pair) |
| **3** | Paralel: Web FE (Geo) — Auth, CRUD master, Pengaduan. Mobile FE (Nika) — Auth, List WO, Detail WO | Split |
| **4** | Web: Manager approve + Laporan. Mobile: SPV assign Staff + Staff submit WO | Split |
| **5** | Integrasi flow lengkap: end-to-end test (Admin → Manager → SPV → Staff → SPV → Manager → Laporan) | Pair |
| **6** | PDF generator + Cloudinary + dokumentasi foto + polish UI | Pair |
| **7** | Bug fixing, SHOULD-HAVE items (dashboard, filter, audit trail) | Pair |
| **8** | Dokumentasi final (BPMN update, ERD finalize, README, demo video), persiapan sidang | Pair |

---

## Risk Register (Disiplin Branch + Kerja Bareng Backend)

Karena **Skenario A** (dua-duanya nyentuh backend), ada 3 risiko utama:

### R1. Merge conflict di migration
- **Mitigasi:** tiap ticket backend (TKT-NN) pakai prefix timestamp unik (`YYYY_MM_DD_HHMMSS_...`). Pair review setiap migration sebelum push.

### R2. Breaking API contract tanpa koordinasi
- **Mitigasi:**
  - OpenAPI spec di `OpenAPI.md` = **single source of truth**
  - Setiap perubahan response shape → update OpenAPI dulu → review bersama → baru implement
  - Commit message wajib prefix `[BE]`, `[FE-WEB]`, `[FE-MOBILE]`

### R3. Dua orang edit service yang sama
- **Mitigasi:**
  - Branch per ticket: `feat/tkt-08-jsonb-form-values` (bukan `feat/nika` atau `feat/geo`)
  - Wajib draft PR kalau ticket durasinya > 2 hari (visibility)
  - Daily 15-menit sync (boleh async via chat) — "aku hari ini kerjakan X, kamu?"

---

## Definition of Done (per Ticket)

Sebuah ticket dianggap **DONE** jika:
1. [ ] Migration berjalan up + down tanpa error
2. [ ] Model relasi ditambahkan + test manual via `tinker`
3. [ ] Service method di-unit-test (kalau ada logika branching)
4. [ ] Controller endpoint sesuai OpenAPI spec
5. [ ] Response 422 untuk validation error (bukan 500)
6. [ ] Seeder masih jalan setelah migration baru
7. [ ] Reviewer (anggota tim lain) approve PR

---

## Definition of Done (Scope TA keseluruhan)

Ketika **semua MUST + minimal 60% SHOULD** tercapai, TA siap disidangkan:
- [ ] Demo bisa menunjukkan end-to-end flow: Pengaduan → WO → Assign → Eksekusi → Review → Approve → Laporan + PDF
- [ ] Semua role (Admin, Manager, SPV, Staff) bisa login dan menjalankan fungsinya
- [ ] ERD Physical sinkron dengan kondisi DB aktual
- [ ] BPMN sinkron dengan implementasi
- [ ] Dokumentasi bisa dipakai onboarding orang lain
- [ ] Unit test minimal 10 test case passing

---

## Catatan Tanya-Jawab Internal

| # | Pertanyaan Penguji (Antisipasi) | Jawaban Singkat |
|---|---|---|
| 1 | Kenapa tidak pakai API PDAM live? | Ketersediaan partner API tidak ter-garansi dalam timeline TA; seeder mock sudah merepresentasikan skema data real. |
| 2 | Kenapa form WO pakai JSONB bukan kolom per field? | Template-driven schema dengan `form_template` + `form_values JSONB` memberi fleksibilitas penambahan jenis WO tanpa migrasi database, sekaligus menghindari anti-pattern EAV (lihat argumentasi Q1). |
| 3 | Kenapa Keuangan tidak ada di departemen? | Keuangan tidak terlibat dalam flow WO lapangan berdasarkan BPMN yang disepakati dengan stakeholder PDAM. |
| 4 | Kenapa cuma 2 role (Manager, SPV, Staff) di mobile? | Web untuk administratif (Admin + Manager approve), Mobile untuk lapangan (SPV assign + Staff eksekusi). Sesuai karakteristik device & pengguna. |
| 5 | Kenapa tidak ada notifikasi real-time? | Dieksklusi sebagai WON'T-HAVE untuk menjaga fokus pada core flow. Bisa ditambahkan di pengembangan lanjutan. |

