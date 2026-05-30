# Flow Work Order — Hulu ke Hilir

> **Sumber keputusan:** `.cursor/ERD_Logical.md` (Q1–Q14) + `.cursor/ERD_Physical.dbml`.
> **Revisi Apr 2026:** Pasca bimbingan Dospem — JSONB form ditolak, diganti **Class Table Inheritance** (3 tabel kategori statis: `wo_meter`, `wo_jaringan`, `wo_infrastruktur`). Role `Admin` dihapus, diganti `Superadmin` yang sekaligus pilih SPV saat create WO (tidak ada lagi tahap "Manager assign SPV").
> **Revisi Mei 2026:** (1) Tambah tabel `workorder_assignment` (1:1 ke `workorder`) untuk mencatat waktu SPV melakukan assign secara eksplisit. (2) Tahap Manager approve final **dihapus** — SPV menjadi approver akhir. Tabel legacy `form_workorder`/`detail_form`/`detail_progress` sudah di-drop.
> **Tujuan dokumen:** memberi gambaran end-to-end siapa melakukan apa, kapan, dan data apa yang berubah di setiap tahap. Setelah flow umum, ada satu bagian khusus dari **POV SPV saat isi form kategori + assign ke Staff**.

---

## 0. Daftar Aktor

| Aktor | Jabatan (`m_jabatan`) | Role (`m_role`) | Peran di alur |
|---|---|---|---|
| **Superadmin** | Kepala Departemen | `superadmin` | Setup master data + **bikin WO** + langsung pilih SPV (FE Web) |
| **Manager** | Manager / Manager Senior | `manager` | **DEPRECATED di flow baru** — dulu approve laporan akhir, sejak Mei 2026 tahap itu dihapus. Masih bisa lihat dashboard, tapi tidak lagi bertindak di lifecycle WO |
| **SPV** | Supervisor | `employee` | **Isi form wo_{kategori}** + assign Staff (tim) + review progres + **approve final** (FE Mobile) |
| **Staff Senior** | Staff Senior | `employee` | Kerjakan WO di lapangan, submit progres + foto (FE Mobile) |
| **Staff** | Staff | `employee` | Kerjakan WO di lapangan, submit progres + foto (FE Mobile) |
| **Pelanggan PDAM** | — | — | Laporkan keluhan (sumber pengaduan) — via API eksternal PDAM |

> **Aturan kunci (Q11):** Superadmin/Manager HANYA bisa bikin/approve WO yang `departemen_id`-nya sama dengan `departemen_id` pegawai mereka. Diterapkan lewat Laravel `Policy` (bukan hanya validasi FormRequest).

> **Pembagian scope capstone:**
> - **FE Web (Next.js)** — Geo: Superadmin create WO + dashboard monitoring + (eksplorasi) lihat laporan final.
> - **FE Mobile (Flutter)** — Nika: SPV isi form kategori + assign Staff + review + approve final, Staff execute WO.

---

## 1. Alur End-to-End (Hulu → Hilir)

Urutan logis dari keluhan masuk sampai laporan resmi terbit. Setiap tahap saya tulis:

- **Aktor** — siapa yang bertindak
- **Aksi bisnis** — yang terjadi
- **Perubahan DB** — tabel yang di-insert/update
- **Status WO** setelah tahap ini

---

### Tahap 1 — Pengaduan Masuk (Hulu)

**Aktor:** Pelanggan PDAM (atau sistem eksternal API PDAM).

**Aksi:**
- Pelanggan lapor keluhan via call center / aplikasi mobile / API PDAM.
- Sistem kita menerima data (atau fetch dari API PDAM periodik).

**Perubahan DB:**
```
INSERT pengaduan (
    external_id, nomor_pelanggan, nama_pelapor, kontak_pelapor, alamat,
    deskripsi, tanggal_lapor, jenis_pengaduan_id,
    status_id = DITERIMA,
    fetched_at, raw_payload  -- audit kalau dari API
)
```

**Status pengaduan:** `DITERIMA`.

> **Catatan (Q7):** Tidak semua WO berasal dari pengaduan. WO internal (mis. maintenance terjadwal) bisa langsung di-bikin di Tahap 3 tanpa `pengaduan_id`.

---

### Tahap 2 — Superadmin Triage Pengaduan

**Aktor:** Superadmin (di departemen Pelayanan).

> ⚠️ **Perubahan dari flow lama:** Role `Admin` dihapus. Superadmin ambil alih tugas triage pengaduan sekaligus create WO (Tahap 3).

**Aksi:**
- Superadmin buka dashboard "Pengaduan masuk".
- Pilih tindakan:
  - **Valid** → teruskan (set `departemen_id` target: Operasional untuk masalah teknis, Pelayanan untuk masalah billing/administratif).
  - **Duplikat** → isi `duplicate_of_id` → pointing ke pengaduan asli.
  - **Tolak** → status `DITOLAK` (bukan urusan PDAM / data tidak valid).

**Perubahan DB:**
```
UPDATE pengaduan SET
    departemen_id = ?,
    status_id     = (DITINDAK | DUPLIKAT | DITOLAK),
    duplicate_of_id = ?  -- kalau duplikat
WHERE id = ?
```

---

### Tahap 3 — Superadmin Bikin Workorder + Langsung Pilih SPV

**Aktor:** Superadmin (FE Web).

> ⚠️ **Perubahan dari flow lama:** Tahap 3 dan Tahap 4 (lama) digabung jadi 1 tahap. Role `Admin` dihapus. Role `Manager` TIDAK lagi assign SPV — Superadmin langsung pilih SPV saat create WO.

**Aksi:**
- Superadmin klik "Buat WO" dari detail pengaduan (atau tombol bebas untuk WO internal).
- Modal muncul: pilih **Jenis WO** (dropdown dari `m_jenis_workorder`) + **KPI target** (dropdown dari `m_kpi`).
- Pilih **SPV** yang akan di-assign (dropdown user dengan jabatan Supervisor di departemen yang sama).
- Isi atribut header WO: judul, lokasi (latitude/longitude/location_id), jenis_lokasi (Kantor/Lapangan), tipe (Normal/Lembur), estimasi durasi, `estimasi_selesai`.
- Submit.

> 📌 **Penting:** Superadmin **TIDAK** mengisi form field kategori (nomor_meter, diameter_pipa, dll) di tahap ini. Field kategori diisi SPV nanti di Tahap 5. Row `wo_meter`/`wo_jaringan`/`wo_infrastruktur` **belum** dibuat di Tahap 3.

**Perubahan DB:**
```
INSERT workorder (
    judul_pekerjaan,
    pengaduan_id          = ? (nullable — internal WO),
    departemen_id,                             -- wajib (policy Q11)
    jenis_workorder_id,                        -- menentukan kategori_form
    tipe_workorder_id,
    jenis_lokasi_id,
    location_id, latitude, longitude,
    waktu_penugasan, estimasi_durasi, unit_waktu, estimasi_selesai,
    status_id             = DITUGASKAN_KE_SPV, -- langsung, tanpa BELUM_DISETUJUI
    created_by_user_id    = superadmin.user_id,
    pic_id                = spv.user_id,       -- SPV dipilih di sini
    kpi_id                = ?                  -- target KPI (1 WO = 1 KPI)
);

INSERT workorder_action (
    workorder_id, action_id = PENUGASAN, actor_id = superadmin.user_id,
    keterangan = 'Superadmin bikin WO + tugaskan ke SPV {nama}', waktu_mulai = now()
);
```

**Status WO:** `DITUGASKAN_KE_SPV`.

> 1 pengaduan bisa menghasilkan N workorder (mis. keluhan kebocoran pipa + meter rusak = 2 WO). Karena itu `pengaduan_id` di `workorder` adalah FK biasa (bukan unique).

---

### Tahap 4 — ~~Manager Assign SPV~~ (DIHAPUS)

> Tahap ini **dihapus** di flow baru. Superadmin sudah langsung pilih SPV di Tahap 3. Manager tidak punya peran assign SPV lagi — cuma approve laporan akhir di Tahap 10.

---

### Tahap 5 — SPV Isi Form Kategori + Assign Staff (Tim)

**Aktor:** SPV yang di-assign di Tahap 3 (`workorder.assigned_to`).

> ⚠️ **Perubahan besar dari flow lama:** Di tahap ini SPV **wajib mengisi form kategori** sebelum bisa assign Staff. Field form-nya ditentukan oleh `m_jenis_workorder.kategori_form` → tabel `wo_meter` / `wo_jaringan` / `wo_infrastruktur`.

Detail lengkap tahap ini ada di **Bagian 3 (POV SPV)** di bawah. Ringkasnya:

**Perubahan DB (atomic transaction):**
```
-- 1. INSERT workorder_assignment (1:1 — catat event assign SPV secara eksplisit)
INSERT workorder_assignment (
    workorder_id,                    -- UNIQUE FK
    spv_user_id  = auth()->id(),     -- SPV yang klik Tugaskan
    assigned_at  = now(),
    catatan_spv  = ?                 -- nullable, instruksi SPV ke tim
);

-- 2. INSERT form kategori (baru muncul di tahap ini, bukan di Tahap 3)
INSERT wo_{kategori} (
    workorder_id,         -- UNIQUE FK ke workorder.id
    {field-field sesuai kategori:
      - wo_meter:         nomor_meter, kondisi_meter_awal, (hasil_kalibrasi nullable)
      - wo_jaringan:      jenis_pipa, diameter_pipa, panjang_pipa, tingkat_kerusakan
      - wo_infrastruktur: nama_aset, jenis_aset, kapasitas, kondisi_awal
    }
);

-- 3. INSERT pivot multi-petugas
INSERT workorder_petugas (workorder_id, user_id, peran) × N staff;

-- 4. UPDATE status WO
UPDATE workorder SET status_id = DITUGASKAN_KE_STAFF WHERE id = ?;

-- 5. Audit
INSERT workorder_action (action = PENUGASAN, actor_id = spv, keterangan = '...');

-- Spawn row progres awal (draft) untuk masing-masing staff? → NO.
-- Progres dibuat saat Staff klik "Mulai" di Tahap 6, bukan di-spawn otomatis.
```

**Validation rule di FormRequest:**
- Semua field required di tabel kategori harus terisi sebelum SPV bisa submit.
- Resolve `kategori_form` dari `m_jenis_workorder.kategori_form` → dispatch ke FormRequest sesuai kategori (`AssignStaffMeterRequest`, `AssignStaffJaringanRequest`, `AssignStaffInfrastrukturRequest`).

**Status WO:** `DITUGASKAN_KE_STAFF`.

---

### Tahap 6 — Staff Mulai Kerja

**Aktor:** Staff (salah satu dari tim).

**Aksi:**
- Staff buka app mobile → list "WO saya".
- Staff validasi lokasi (GPS match dengan `workorder.location_id` + radius).
- Klik tombol **"Mulai"**.

**Perubahan DB:**
```
INSERT progress_workorder (
    workorder_id, "order" = 1,
    tipe_progress_id = MULAI,
    status_id        = SUBMITTED,
    submitted_by_user_id = staff.user_id,
    waktu_submit     = now()
);

UPDATE workorder SET status_id = IN_PROGRESS WHERE id = ?;
```

**Status WO:** `IN_PROGRESS`.

---

### Tahap 7 — Staff Submit Progres Lanjutan

**Aktor:** Staff.

**Aksi:**
- Sepanjang pengerjaan, Staff bisa submit progres antara (opsional, mis. "Sudah sampai lokasi", "Material sudah tersedia").
- Upload foto hasil pengerjaan tahap tersebut.

**Perubahan DB (setiap submit):**
```
INSERT progress_workorder (
    workorder_id, "order" = N,
    tipe_progress_id = PROGRESS,
    status_id        = SUBMITTED,
    submitted_by_user_id = staff.user_id,
    hasil_pengerjaan = 'narasi...',
    waktu_submit     = now()
);

INSERT dokumentasi_progress (
    progress_workorder_id, url = 'cloudinary://...', jenis = HASIL_KERJA
) × banyak foto;
```

Status WO **tidak berubah** — tetap `IN_PROGRESS`.

---

### Tahap 8 — Staff Submit "Selesai"

**Aktor:** Staff.

**Aksi:**
- Staff yakin pekerjaan tuntas.
- Klik **"Selesai"** → upload foto final + isi narasi hasil akhir + update **field hasil akhir** di `wo_{kategori}` kalau ada (mis. `kondisi_meter_akhir`, `hasil_kalibrasi`, `tindakan_perbaikan`, `hasil_inspeksi`).

**Perubahan DB:**
```
INSERT progress_workorder (
    workorder_id, "order" = N,
    tipe_progress_id = SELESAI,
    status_id        = SUBMITTED,
    submitted_by_user_id = staff.user_id,
    hasil_pengerjaan = 'narasi final...',
    waktu_submit     = now()
);

INSERT dokumentasi_progress (... jenis = HASIL_KERJA) × foto final;

-- Optional: update field hasil akhir di tabel kategori
UPDATE wo_{kategori}
SET
    {field hasil akhir:
      - wo_meter:         kondisi_meter_akhir, hasil_kalibrasi
      - wo_jaringan:      tindakan_perbaikan, hasil_inspeksi
      - wo_infrastruktur: kondisi_akhir, tindakan
    }
WHERE workorder_id = ?;

UPDATE workorder SET status_id = PENGECEKAN WHERE id = ?;
-- "PENGECEKAN" = MENUNGGU_VERIFIKASI_SPV
```

**Status WO:** `PENGECEKAN` (menunggu review SPV).

---

### Tahap 9 — SPV Review Progres "Selesai" (Q12)

**Aktor:** SPV (pic_id).

> ⚠️ **Revisi Mei 2026:** Sebelumnya Tahap 9a (Terima) mengarah ke status `MENUNGGU_APPROVAL_MANAGER` → Manager approve di Tahap 10. Sekarang Manager approve **dihapus**; SPV langsung jadi approver final (lihat Tahap 10 baru).

SPV punya **3 tombol** di UI review:

#### 9a. **Terima**
```
UPDATE progress_workorder SET
    status_id          = VERIFIED,
    reviewed_by_user_id = spv.user_id,
    reviewed_at        = now()
WHERE id = ? (row SELESAI Staff);

-- Tidak lagi ke MENUNGGU_APPROVAL_MANAGER — langsung lanjut ke Tahap 10 (SPV Approve)
-- Status intermediate ini bisa dibiarkan di PENGECEKAN sampai SPV klik "Approve"
-- di UI yang sama (satu layar review), atau di-fast-track ke Tahap 10 otomatis.
-- Pilihan design ada di ticketing Mei 2026.
```

**Status WO:** tetap `PENGECEKAN` (menunggu SPV klik Approve di Tahap 10).

#### 9b. **Minta Revisi**
Staff harus revisi field tertentu saja.
```
-- Update row SELESAI Staff → ditandai "butuh revisi"
UPDATE progress_workorder SET
    status_id          = REVISI_REQUESTED,
    reviewed_by_user_id = spv.user_id,
    reviewed_at        = now(),
    alasan_penolakan   = 'Foto hasil masih buram, mohon retake',
    field_to_revise    = ['foto_hasil', 'tekanan_akhir']  -- JSONB
WHERE id = ? (row SELESAI Staff);

-- APPEND row baru bertanda REVISI (bukan delete/edit row lama — BR-4)
INSERT progress_workorder (
    workorder_id, "order" = N+1,
    tipe_progress_id = REVISI,
    status_id        = SUBMITTED,
    submitted_by_user_id = spv.user_id,   -- SPV yang request
    hasil_pengerjaan = 'SPV minta revisi di field: ...'
);

UPDATE workorder SET status_id = IN_PROGRESS WHERE id = ?;  -- balik ke Staff
```

**Status WO:** `IN_PROGRESS` lagi, Staff lanjut ke Tahap 8 (submit ulang SELESAI dengan field_to_revise diperbaiki).

#### 9c. **Tolak (Final)**
Pekerjaan Staff tidak memenuhi standar, WO terminasi.
```
UPDATE progress_workorder SET
    status_id          = DITOLAK_SPV,
    reviewed_by_user_id = spv.user_id,
    reviewed_at        = now(),
    alasan_penolakan   = 'Pengerjaan tidak sesuai SOP, perlu WO baru'
WHERE id = ?;

INSERT progress_workorder (
    workorder_id, "order" = N+1,
    tipe_progress_id = DITOLAK,
    status_id        = SUBMITTED,
    submitted_by_user_id = spv.user_id,
    hasil_pengerjaan = 'Ditolak: ...'
);

UPDATE workorder SET status_id = DITOLAK_SPV WHERE id = ?;  -- terminal
```

**Status WO:** `DITOLAK_SPV` (final, tidak ada laporan terbit).

---

### Tahap 10 — SPV Approve Akhir (Revisi Mei 2026)

**Aktor:** SPV yang sama dengan `workorder.assigned_to`.

> ⚠️ **Revisi besar Mei 2026:** Tahap Manager approve **dihapus** dari lifecycle. SPV yang review di Tahap 9 juga yang mem-approve final di tahap ini. Alasan: menyederhanakan rantai approval (lihat capstone scope — Manager tidak lagi aktif terlibat di workflow).

**Aksi:** SPV cek ulang hasil final (data `wo_{kategori}` + foto + narasi progres) lalu klik **"Approve"**. Dari UI yang sama dengan Tahap 9, atau di screen terpisah sesuai design FE.

```
UPDATE workorder SET
    status_id           = SELESAI,
    approved_by_user_id = spv.user_id,
    approved_at         = now(),
    approval_notes      = 'OK'
WHERE id = ?;

INSERT workorder_action (action = APPROVE, actor_id = spv.user_id, ...);
```

Trigger Laravel Event `WorkorderApproved` → listener `IssueWorkorderReport` (Tahap 11).

**Status WO:** `SELESAI` (terminal sukses). Laporan terbit otomatis di Tahap 11.

> Catatan: kolom `workorder.approved_by_user_id` tetap dipertahankan, isinya sekarang user_id SPV (bukan Manager). Kolom `laporan_workorder.approved_by_user_id` dan `catatan_manager` juga ikut — untuk `catatan_manager` sementara di-leave NULL sampai ada keputusan rename / drop kolom (ticket terpisah).

> Status `MENUNGGU_APPROVAL_MANAGER` dan `DITOLAK_MANAGER` di `m_status`: **dibiarkan di DB sebagai deprecated** (tidak aktif di lifecycle baru, tapi tidak di-delete supaya row lama yang punya status ini tidak broken). Seeder baru bisa set `aktif = false`.

---

### Tahap 11 — Laporan Resmi Terbit (Hilir)

**Aktor:** Sistem (event listener, bukan user).

**Aksi:** Event listener `IssueWorkorderReport` jalan otomatis saat WO status → `SELESAI`:

1. Generate `nomor_laporan` = `LAP-WO-2026-0001` (sequence per tahun).
2. Resolve `kategori_form` dari `workorder.jenis_workorder_id` → JOIN ke `m_jenis_workorder`.
3. Snapshot row dari `wo_{kategori}` → `laporan_workorder.hasil_akhir_snapshot` (flat JSON via `row_to_json()`).
4. Snapshot daftar petugas → `laporan_workorder.petugas_snapshot` `[{user_id, nama, nip}, ...]`.
5. Calculate KPI result dari `progress_workorder` timestamps (via `KpiCalculatorService::calculate($wo)`).
6. Insert row laporan.
7. Dispatch Job async → render PDF via **domPDF** → upload Cloudinary → update `pdf_url`.

**Perubahan DB:**
```sql
INSERT laporan_workorder (
    workorder_id, nomor_laporan, tanggal_terbit = now(),
    ringkasan_pekerjaan,
    hasil_akhir_snapshot = (
        -- contoh untuk WO kategori meter
        SELECT row_to_json(t)
        FROM wo_meter t
        WHERE workorder_id = :wo_id
    ),
    petugas_snapshot     = [...],
    issued_by_user_id    = spv.user_id,    -- SPV (sama dengan approved_by_user_id di flow baru)
    approved_by_user_id  = spv.user_id,    -- Revisi Mei 2026: SPV, bukan Manager
    approved_at          = workorder.approved_at,
    pdf_url              = null  -- diisi kemudian oleh job async
);
```

Side effect: Event listener juga update `pengaduan.status_id`:
- Semua WO dari 1 pengaduan SELESAI → `pengaduan.status_id = SELESAI`.
- Ada minimal 1 WO aktif → `DITINDAK` (sudah di-set di Tahap 2).

---

## 2. Diagram Status Flow WO

```
         [Superadmin bikin WO + langsung pilih SPV]
            │
            ▼
       DITUGASKAN_KE_SPV                          ◄── status awal
            │
            │  [SPV isi form wo_{kategori} + assign Staff (atomic, include workorder_assignment)]
            ▼
       DITUGASKAN_KE_STAFF
            │
            │  [Staff klik Mulai]
            ▼
       IN_PROGRESS ◄──────────────────────┐
            │                             │
            │  [Staff submit Selesai]     │ [SPV Minta Revisi]
            ▼                             │
       PENGECEKAN (MENUNGGU_VERIFIKASI_SPV)
            │
            ├──[SPV Terima + Approve]──► SELESAI ──► (Laporan terbit)
            │
            └──[SPV Tolak] ────────────► DITOLAK_SPV (terminal)
```

Status terminal: `SELESAI`, `DITOLAK_SPV`.

> **Perubahan Mei 2026:** Status `MENUNGGU_APPROVAL_MANAGER` dan `DITOLAK_MANAGER` dihapus dari lifecycle aktif (row `m_status`-nya dibiarkan dengan `aktif = false` untuk kompatibilitas row lama). SPV jadi terminator path sukses.
> **Perubahan Apr 2026:** status `BELUM_DISETUJUI` (menunggu Manager assign SPV) **DIHAPUS**. WO langsung lahir dengan status `DITUGASKAN_KE_SPV` karena Superadmin yang bikin sekaligus pilih SPV.

---

## 3. POV: SPV Assign ke Staff (Detail)

Bagian ini memperluas Tahap 5 dari perspektif SPV. Ini paling rame secara interaksi karena SPV berurusan dengan banyak hal:

### 3.1 Trigger: SPV Dapat Notifikasi

Segera setelah Manager selesai di Tahap 4:

- Sistem push notification ke SPV (lewat FCM kalau mobile / WebSocket/Pusher kalau web).
- Badge di dashboard SPV bertambah.
- Email opsional (kalau sudah ada layer notification — cek `.cursor/Implement_this_ticketing.md`).

### 3.2 Dashboard SPV: "WO Masuk"

SPV buka menu **"WO Saya"** → filter default: `status_id = DITUGASKAN_KE_SPV AND assigned_to = me`.

Query backend kira-kira:
```sql
SELECT w.*, ms.nama AS status_nama, mjw.nama AS jenis
FROM workorder w
JOIN m_status         ms  ON ms.id  = w.status_id
JOIN m_jenis_workorder mjw ON mjw.id = w.jenis_workorder_id
WHERE w.assigned_to = :spv_user_id
  AND ms.kode = 'DITUGASKAN_KE_SPV'
ORDER BY w.created_at DESC;
```

### 3.3 SPV Buka Detail WO

Informasi yang SPV lihat:
- Judul, jenis WO, tipe (Normal/Lembur), jenis lokasi
- Alamat + koordinat (+ peta)
- Estimasi durasi + deadline (`estimasi_selesai`)
- Deskripsi pengaduan terkait (kalau `pengaduan_id` tidak null)
- **Form kategori kosong** (wo_meter/wo_jaringan/wo_infrastruktur) yang akan diisi SPV — field list di-resolve dari `m_jenis_workorder.kategori_form`
- Daftar Staff kandidat di departemennya

Kandidat Staff di-filter dari:
```sql
SELECT u.id, p.nama, p.nip, j.nama AS jabatan
FROM users u
JOIN m_pegawai p ON p.id = u.pegawai_id
JOIN m_jabatan j ON j.id = p.jabatan_id
WHERE p.departemen_id = (
    SELECT p2.departemen_id FROM users u2
    JOIN m_pegawai p2 ON p2.id = u2.pegawai_id
    WHERE u2.id = :spv_user_id
)
  AND j.nama IN ('Staff', 'Senior Staff')
  -- Opsional: exclude yang lagi sibuk WO lain
  AND u.id NOT IN (
      SELECT wp.user_id FROM workorder_petugas wp
      JOIN workorder w ON w.id = wp.workorder_id
      JOIN m_status ms ON ms.id = w.status_id
      WHERE ms.kode IN ('IN_PROGRESS', 'PENGECEKAN')
  );
```

### 3.4 SPV Pilih Tim (Multi-Petugas — Q3)

UI: multi-select dengan opsi role (koordinator / anggota).

Contoh:
- Budi (Senior Staff) — **Koordinator**
- Andi (Staff) — Anggota
- Dewi (Staff) — Anggota

Business rule:
- **Minimal 1 petugas** per WO (FormRequest validation).
- **Max 1 koordinator** — sisanya anggota atau null.
- Satu user tidak bisa dipilih 2x (dicegah di DB lewat `unique(workorder_id, user_id)` di tabel `workorder_petugas`).

### 3.5 SPV Klik "Tugaskan" (Submit form + assign Staff dalam 1 aksi)

Transaksi atomik (database transaction). Urutan operasi:

```php
DB::transaction(function () use ($wo, $formData, $selectedStaff, $catatanSpv) {
    // 0. INSERT workorder_assignment (1:1 — catat event assign SPV secara eksplisit)
    //    Tabel ini menjawab pertanyaan "kapan SPV melakukan assign untuk WO ini?"
    //    tanpa perlu JOIN ke workorder_action.
    \App\Models\WorkorderAssignment::create([
        'workorder_id' => $wo->id,
        'spv_user_id'  => auth()->id(),
        'assigned_at'  => now(),
        'catatan_spv'  => $catatanSpv, // nullable
    ]);

    // 1. INSERT form kategori (baru muncul di tahap ini, BUKAN di Tahap 3)
    //    Dispatch berdasarkan m_jenis_workorder.kategori_form
    $kategori = $wo->jenisWorkorder->kategori_form; // 'meter' | 'jaringan' | 'infrastruktur'
    $modelClass = [
        'meter'         => \App\Models\WoMeter::class,
        'jaringan'      => \App\Models\WoJaringan::class,
        'infrastruktur' => \App\Models\WoInfrastruktur::class,
    ][$kategori];

    $modelClass::create(array_merge(
        ['workorder_id' => $wo->id],
        $formData // field-field sesuai kategori, sudah divalidasi di FormRequest
    ));

    // 2. INSERT pivot multi-petugas
    foreach ($selectedStaff as $staff) {
        DB::table('workorder_petugas')->insert([
            'workorder_id' => $wo->id,
            'user_id'      => $staff['user_id'],
            'peran'        => $staff['peran'], // 'koordinator' | 'anggota' | null
            'created_at'   => now(),
            'updated_at'   => now(),
        ]);
    }

    // 3. UPDATE status WO
    DB::table('workorder')
        ->where('id', $wo->id)
        ->update([
            'status_id'  => Status::kode('DITUGASKAN_KE_STAFF')->id,
            'updated_at' => now(),
        ]);

    // 4. Audit trail
    DB::table('workorder_action')->insert([
        'workorder_id'  => $wo->id,
        'action_id'     => Action::kode('PENUGASAN')->id,
        'actor_id'      => auth()->id(), // SPV
        'keterangan'    => sprintf(
            'SPV isi form %s + tugaskan %d petugas: %s',
            $kategori,
            count($selectedStaff),
            collect($selectedStaff)->pluck('nama')->join(', ')
        ),
        'waktu_mulai'   => now(),
        'created_at'    => now(),
        'updated_at'    => now(),
    ]);
});
```

Kalau ada satu operasi gagal (mis. constraint `unique(workorder_id, user_id)` violated atau `wo_{kategori}.workorder_id` sudah ada), seluruh transaksi rollback.

### 3.6 Notifikasi ke Staff

Setelah transaksi commit, dispatch **Notification Job** (async, lewat queue):

- Target: semua `user_id` di `workorder_petugas` untuk WO ini.
- Channel: FCM (mobile push) + database notification (untuk badge in-app).
- Payload: judul WO + lokasi + koordinator siapa + deadline.

### 3.7 Sisi DB: Apa yang Berubah

| Tabel | Operasi | Jumlah Row |
|---|---|---|
| `workorder_assignment` | INSERT | 1 (1:1 dengan WO — catat waktu + SPV + catatan) |
| `wo_meter` / `wo_jaringan` / `wo_infrastruktur` | INSERT | 1 (di salah satu tabel, tergantung `kategori_form`) |
| `workorder_petugas` | INSERT | N (jumlah petugas) |
| `workorder` | UPDATE | 1 (status_id → DITUGASKAN_KE_STAFF) |
| `workorder_action` | INSERT | 1 (action PENUGASAN oleh SPV) |
| `progress_workorder` | **Tidak ada** | Progres baru dibuat saat Staff klik "Mulai" di Tahap 6 |

### 3.8 Edge Case & Business Rules

| Skenario | Handling |
|---|---|
| SPV pilih Staff dari departemen lain | FormRequest reject (policy check: `staff.pegawai.departemen_id == wo.departemen_id`) |
| SPV pilih 0 petugas | FormRequest reject (`required\|min:1`) |
| SPV pilih 2 koordinator | FormRequest reject (custom rule) |
| SPV submit form kategori tanpa mengisi field required | FormRequest reject (dispatch ke `AssignStaffMeterRequest` / `AssignStaffJaringanRequest` / `AssignStaffInfrastrukturRequest` sesuai kategori) |
| SPV coba submit form kategori yang row-nya sudah ada (duplicate) | DB reject via constraint `unique(workorder_id)` di `wo_{kategori}` |
| SPV coba assign WO yang bukan `pic_id` nya | Laravel Policy `WorkorderPolicy::assignStaff` return `false` |
| SPV coba assign saat WO status bukan `DITUGASKAN_KE_SPV` | Policy reject |
| Tim ada yang berhalangan mendadak setelah assign | SPV punya menu "Ganti Petugas" — DELETE dari pivot + INSERT baru (tetap dalam 1 WO, bukan bikin WO baru). Append `workorder_action` kode `PENUGASAN` lagi dengan keterangan "Ganti petugas: A → B" |
| WO tipe Lembur | Tambahan: SPV wajib link ke `lembur_spl` yang sudah di-approve dulu (cek `workorder.lembur_spl_id`) |

### 3.9 Output bagi SPV

Setelah assign berhasil, SPV melihat:
- Status WO di UI berubah jadi **"Dalam Penugasan — menunggu Staff memulai"**.
- Kolom "Petugas" terisi dengan daftar nama + peran.
- Timeline (pakai data `workorder_action`) bertambah entri **"[SPV Nama] menugaskan: Budi (Koordinator), Andi, Dewi"**.
- WO ini berpindah dari tab **"Perlu Assign"** ke tab **"Berjalan"**.

---

## 4. Checklist Implementasi (untuk Backend)

Task minimal untuk dukung flow ini, merujuk ke `.cursor/Implement_this_ticketing.md`:

**Migration & Model (dasar):**
- [ ] Migration `alter_m_jenis_workorder_add_kategori_form` (enum: meter/jaringan/infrastruktur)
- [ ] Migration `create_wo_meter_table` — 1:1 workorder, field: nomor_meter, kondisi_awal/akhir, hasil_kalibrasi
- [ ] Migration `create_wo_jaringan_table` — 1:1 workorder, field: jenis_pipa, diameter, panjang, tingkat_kerusakan, tindakan, hasil_inspeksi
- [ ] Migration `create_wo_infrastruktur_table` — 1:1 workorder, field: nama_aset, jenis_aset, kapasitas, kondisi_awal/akhir, jadwal_pemeliharaan, tindakan
- [ ] Migration `alter_workorder_drop_manager_id`
- [ ] Migration `alter_workorder_add_kpi_id` FK ke `m_kpi`
- [ ] Migration `alter_m_kpi_add_kode_deskripsi`
- [x] Migration `drop_legacy_form_tables` — drop `detail_progress` → `detail_form` → `form_workorder` (Mei 2026)
- [x] Migration `create_workorder_assignment_table` (Mei 2026)
- [x] Model `WorkorderAssignment` + relasi `hasOne` di `Workorder`
- [ ] Model `WoMeter`, `WoJaringan`, `WoInfrastruktur` + relasi `belongsTo` ke Workorder
- [ ] Update `JenisWorkorder` model: cast `kategori_form`, helper `resolveKategoriModel()`
- [ ] Update `Workorder` model: tambah relasi `woMeter`, `woJaringan`, `woInfrastruktur`, `kpi`

**Seeders:**
- [ ] `JenisWorkorderSeeder` — seed 10 jenis dengan `kategori_form` (3 meter + 4 jaringan + 3 infrastruktur)
- [ ] `KpiSeeder` — seed 4 KPI starter (ON_TIME, RESPONSE_TIME, ZERO_REVISI, COMPLIANCE_FOTO)
- [ ] `StatusSeeder` — tambah `DITUGASKAN_KE_SPV`, `DITUGASKAN_KE_STAFF`, `MENUNGGU_APPROVAL_MANAGER`, `REVISI_REQUESTED`, `DITOLAK_SPV`, `DITOLAK_MANAGER`
- [ ] `TipeProgressSeeder` — tambah `REVISI`, `DITOLAK`
- [ ] `ActionSeeder` — tambah `APPROVE`, `REJECT`, `REVISI`

**Scope Nika (Tahap 5–10 baru):**
- [ ] Policy `WorkorderPolicy::assignStaff` (pic_id check + status = DITUGASKAN_KE_SPV)
- [ ] Policy `WorkorderPolicy::submitProgress`, `reviewProgress`, `approve`
- [ ] Controller `WorkorderAssignController::assignStaff` — endpoint `POST /v1/workorder/{id}/assign-staff` dengan body `{ form_kategori: {...}, petugas: [{user_id, peran}], catatan_spv?: string }`
- [ ] Service `WorkorderAssignService::assignStaff` — transaction 5 operasi di Section 3.5 (termasuk INSERT `workorder_assignment`)
- [ ] FormRequest `AssignStaffMeterRequest`, `AssignStaffJaringanRequest`, `AssignStaffInfrastrukturRequest` — dispatch dinamis berdasarkan `kategori_form`
- [ ] Controller `ProgressWorkorderController::start/submit/finish` (Tahap 6–8)
- [ ] Controller `ProgressWorkorderController::review` (Tahap 9: terima/revisi/tolak)
- [ ] Controller `WorkorderController::approve` — Tahap 10 baru (SPV approve, bukan Manager)
- [ ] Notification class `StaffAssignedToWorkorderNotification` (channel: FCM + database)

**Scope Geo (Tahap 3, 11) — untuk reference saja:**
- [ ] Endpoint `GET /api/v1/jenis-workorder/{id}/schema` — return field list kategori untuk UI Mobile render form kosong
- [ ] Controller `WorkorderController::store` (Tahap 3)
- [ ] Event + Listener `WorkorderApproved` → `IssueWorkorderReport` (Tahap 11)
- [ ] Service `KpiCalculatorService::calculate($wo)` — switch berdasarkan `kpi.kode`

---

## 5. Referensi

- ERD Logical: `.cursor/ERD_Logical.md`
- ERD Physical (DBML): `.cursor/ERD_Physical.dbml`
- Ticket backend: `.cursor/Implement_this_ticketing.md`
- Scope & timeline: `.cursor/MoSCow.md`
- API contract: `.cursor/OpenAPI.md`
