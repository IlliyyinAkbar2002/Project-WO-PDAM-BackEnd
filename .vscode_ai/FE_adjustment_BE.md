# FE Adjustment — Backend Contract (Flutter Mobile)

Dokumen ini adalah hasil sinkronisasi BE setelah testing API E2E (Create WO → SPV approve). Tujuan: AI agent yang mengerjakan Flutter mobile FE bisa langsung menyusun payload yang sesuai dan tidak perlu menebak struktur BE.

Backend stack: Laravel 8 + Sanctum + PostgreSQL.
Base URL: `{{host}}/api/v1`
Auth scheme: `Authorization: Bearer {sanctum_token}` untuk semua endpoint kecuali `/auth/login` dan `/auth/register`.

---

## 0. Konvensi umum

### Headers wajib (selain `/auth/login`)
```
Authorization: Bearer {token}
Accept: application/json
Content-Type: application/json    # atau multipart/form-data jika upload file
```

### Response shape
- **Sukses**: body bervariasi per endpoint (lihat masing-masing). Status code: `200` (read/update), `201` (create), `204` (delete).
- **Error validasi (422)**:
  ```json
  {
    "message": "The given data was invalid.",
    "errors": {
      "field_name": ["Pesan error per field"]
    }
  }
  ```
- **Error business logic (422/403)**:
  ```json
  { "error": "Pesan error spesifik dari controller" }
  ```
- **Auth gagal (401)**:
  ```json
  { "message": "Unauthenticated." }
  ```

### Tipe data PostgreSQL → Dart
| BE column type | Dart |
|---|---|
| `int8`/`bigint`/`id` | `int` |
| `numeric(p,s)` / `decimal` | `double` |
| `text` / `varchar` | `String` |
| `boolean` | `bool` |
| `timestamp` / `timestamptz` | `DateTime` (parse ISO 8601) |
| `date` | `DateTime` (format `YYYY-MM-DD`) |
| `json` / `jsonb` | `Map<String, dynamic>` atau `List` |

### Indonesian column names
Semua field nama BE pakai Indonesia (`nama_workorder`, `tanggal_mulai`, `petugas`, dll). Jangan camelCase di payload — Laravel snake_case strict.

---

## 1. Auth

### 1.1 Login
**Endpoint**: `POST /api/v1/auth/login`
**Headers**: tidak butuh Bearer.

**Body**
```json
{
  "email": "david123@gmail.com",
  "password": "password"
}
```

**Response 200**
```json
{
  "message": "Login berhasil",
  "token": "12|aBcDeFgHiJkLmNoPqRsTuVwXyZ...",
  "user": {
    "id": 5,
    "email": "david123@gmail.com",
    "role_id": 3,
    "pegawai": {
      "id": 5,
      "nama": "David",
      "nip": "1234567894",
      "jabatan": { "id": 3, "kode": "SENIOR_STAFF", "nama": "Senior Staff" },
      "departemen": { "id": 2, "nama": "..." }
    }
  }
}
```

**Test credentials (dari seeder)**
| Email | Password | Role | Jabatan |
|---|---|---|---|
| `iyyin@gmail.com` | `password` | employee | SPV |
| `aulya@gmail.com` | `password` | employee | SPV |
| `david123@gmail.com` | `password` | employee | Senior Staff (PIC) |
| `budi123@gmail.com` | `password` | employee | Staff |

### 1.2 Me (current user)
**Endpoint**: `GET /api/v1/auth/me`

Returns same shape as login `user` object — useful untuk refresh state setelah cold start.

### 1.3 Logout
**Endpoint**: `POST /api/v1/auth/logout` → 204, token Sanctum dicabut.

---

## 2. Master / Reference data (read-only)

FE butuh ini untuk dropdown, filter, dan rendering status.

### 2.1 Jenis Workorder
`GET /api/v1/jenis-workorder`
```json
[
  { "id": 1, "nama": "...", "kategori_form": "jaringan" },
  { "id": 2, "nama": "...", "kategori_form": "meter" }
]
```
**`kategori_form`** menentukan form yang harus diisi SPV: `meter` | `jaringan` | `infrastruktur`.

### 2.2 Pegawai (untuk SPV pilih staff saat assign)
`GET /api/v1/pegawai`
`GET /api/v1/pegawai/filter?jabatan_kode=STAFF&departemen_id=2`

### 2.3 Master Location (geofence list)
`GET /api/v1/master-location`
```json
[
  { "id": 1, "nama": "...", "latitude": -7.25, "longitude": 112.76, "radius_meter": 100 }
]
```

---

## 3. Workorder

### 3.1 List
**Endpoint**: `GET /api/v1/workorder`
**Filter via query string** (semua optional): `status_kode`, `prioritas`, `assigned_to`, `tanggal_mulai`, `tanggal_selesai`.

**Auth rule**: Non-superadmin user hanya melihat WO dimana mereka sebagai `assigned_to` (SPV) **atau** salah satu member di assignment (staff).

### 3.2 Show
**Endpoint**: `GET /api/v1/workorder/{id}`

Response includes eager-loaded:
- `jenis_workorder` (with `kategori_form`)
- `status` (with `kode`)
- `assigned_to` (user object — SPV)
- `wo_meter` **atau** `wo_jaringan` **atau** `wo_infrastruktur` (sesuai kategori, hanya satu yang non-null)
- `workorder_assignment` (with `members.user.pegawai`, `location`, `spv.pegawai`)
- `latest_freeze` (jika WO pernah di-freeze)

### 3.3 Create (Superadmin only)
**Endpoint**: `POST /api/v1/workorder`
**Role**: superadmin

**Body**
```json
{
  "nama_workorder": "Perbaikan Kebocoran Pipa Distribusi",
  "deskripsi": "Kebocoran pipa distribusi utama di Jl. Merdeka",
  "tanggal_mulai": "2026-05-25",
  "jenis_workorder_id": 1,
  "lokasi": "Jl. Merdeka No. 45, Blok B",
  "prioritas": "tinggi",
  "assigned_to": 3
}
```

**⚠️ PENTING — field yang JANGAN dikirim di create WO:**
- `wo_meter`, `wo_jaringan`, `wo_infrastruktur`: **diisi SPV** di step assign-staff, bukan di create. Kalau dikirim, di-drop validator.
- `status_id`: auto-set ke `DITUGASKAN_KE_SPV`.
- `progres_persen`, `tanggal_selesai`: auto-computed BE.

**`prioritas` enum**: `rendah` | `sedang` | `tinggi` | `darurat`.

### 3.4 Assignment detail
**Endpoint**: `GET /api/v1/workorder/{id}/assignment`

Return assignment dengan SPV, members, dan location. Kalau belum di-assign, return 404 dengan message `"Workorder ini belum memiliki assignment."`.

---

## 4. Workflow: SPV Assign Staff

**Endpoint**: `POST /api/v1/workorder/{id}/assign-staff`
**Role**: SPV (user_id harus match `workorder.assigned_to`)
**Pre-condition**: WO status = `DITUGASKAN_KE_SPV`, belum pernah di-assign.

### Body (jaringan)
```json
{
  "kategori_form": "jaringan",
  "deskripsi": "Tim turun pagi",
  "tanggal_mulai": "2026-05-25",
  "tanggal_selesai": "2026-05-26",
  "estimasi_selesai": "2026-05-26",
  "latitude": -7.250445,
  "longitude": 112.768845,
  "accuracy": 12.5,
  "nama_lokasi": "Jl. Merdeka No. 45",

  "petugas": [
    { "user_id": 5, "peran": "koordinator" },
    { "user_id": 6, "peran": "anggota" }
  ],

  "form_kategori": {
    "jenis_pipa": "PVC",
    "diameter_pipa": 150.00,
    "panjang_pipa": 12.50,
    "tingkat_kerusakan": "sedang",
    "tindakan_perbaikan": "Potong segmen bocor, pasang sambungan repair clamp",
    "hasil_inspeksi": null
  }
}
```

### Body (meter)
```json
{
  "kategori_form": "meter",
  "petugas": [ { "user_id": 5, "peran": "koordinator" } ],
  "form_kategori": {
    "nomor_meter": "MTR-2026-0001",     // REQUIRED
    "kondisi_meter_awal": "rusak",
    "kondisi_meter_akhir": null,
    "hasil_kalibrasi": null
  }
}
```

### Body (infrastruktur)
```json
{
  "kategori_form": "infrastruktur",
  "petugas": [ { "user_id": 5, "peran": "koordinator" } ],
  "form_kategori": {
    "nama_aset": "Pompa booster Blok C",   // REQUIRED
    "jenis_aset": "pompa",                  // REQUIRED
    "kapasitas": "5 L/s",
    "kondisi_awal": "tidak berfungsi",
    "kondisi_akhir": null,
    "jadwal_pemeliharaan": "2026-06-01",
    "tindakan": "Service motor + ganti impeller"
  }
}
```

### Validation rules
| Field | Rule |
|---|---|
| `kategori_form` | nullable, in:`meter`,`jaringan`,`infrastruktur` (fallback ke `jenis_workorder.kategori_form`) |
| `form_kategori` | required, array. Boleh dikirim flat di root, controller auto-wrap |
| `petugas` | required, array, min 1 |
| `petugas.*.user_id` | required, exists in `users` |
| `petugas.*.peran` | nullable, in:`koordinator`,`anggota` |
| `latitude` | nullable, numeric, between:-90,90 |
| `longitude` | nullable, numeric, between:-180,180 |
| `tanggal_mulai`, `tanggal_selesai`, `estimasi_selesai` | nullable, date |

### Aturan PIC
`peran: "koordinator"` → BE set `is_pic = true`. **Wajib ada minimal satu koordinator** kalau pekerjaan ini akan butuh submit SELESAI (hanya PIC + Senior Staff yang bisa).

### Side effects
- INSERT `workorder_assignment` (1 row per WO)
- INSERT `wo_assignment_member` (N rows)
- INSERT `wo_meter` / `wo_jaringan` / `wo_infrastruktur` (1 row, sesuai kategori)
- INSERT `m_location` (1 row, kalau lat/lng dikirim, radius default 100m)
- INSERT `workorder_action` dengan kode `PENUGASAN`
- `workorder.status_id` → `DITUGASKAN_KE_STAFF`

### Errors
| HTTP | Trigger |
|---|---|
| 403 | Login bukan SPV `assigned_to` |
| 422 | Status WO bukan `DITUGASKAN_KE_SPV` / WO sudah di-assign / field kategori kurang (`jenis_pipa`/`nomor_meter`/`nama_aset`+`jenis_aset`) |

---

## 5. Workflow: Staff Start Kerja

**Endpoint**: `POST /api/v1/progress-workorder/start`
Juga menerima `PUT` / `PATCH` untuk kompatibilitas client.
**Role**: staff yang ada di `petugas` list assignment.
**Pre-condition**: WO status `DITUGASKAN_KE_STAFF` atau `IN_PROGRESS`.

### Body JSON (tanpa foto)
```json
{
  "workorder_id": 1,
  "hasil_pengerjaan": "Mulai isolasi area kebocoran",
  "latitude": -7.250450,
  "longitude": 112.768855,
  "accuracy": 8.2
}
```

### Body multipart/form-data (dengan foto)
```
workorder_id: 1
hasil_pengerjaan: Mulai isolasi area kebocoran
latitude: -7.250450
longitude: 112.768855
accuracy: 8.2
foto[]: <File jpeg/png/jpg, max 2048KB>
foto[]: <File jpeg/png/jpg, max 2048KB>
```

### Validation
| Field | Rule |
|---|---|
| `workorder_id` | required, exists |
| `hasil_pengerjaan` | nullable, string, max 255 |
| `latitude`, `longitude` | required, numeric |
| `accuracy` | nullable, numeric |
| `foto[]` | nullable array, masing-masing image jpeg/png/jpg, max 2048KB |

### Side effects
- INSERT `progress_workorder` (`tipe_progress=MULAI`, `status=SUBMITTED`, `order=1`, lat/lng disimpan)
- INSERT `dokumentasi_progress` per file foto (`jenis=HASIL_KERJA`, URL relatif `dokumentasi_progress/{filename}`)
- `workorder.status_id` → `IN_PROGRESS`
- INSERT `workorder_action` `kode=MULAI_KERJA`

### Response 201
```json
{
  "progress": {
    "id": 1,
    "order": 1,
    "tipe_progress_id": 1,
    "status_id": 10,
    "latitude": -7.25045,
    "longitude": 112.768855,
    "accuracy": 8.2,
    "waktu_submit": "2026-05-25T07:15:00Z",
    "dokumentasi_progress": [
      { "id": 1, "url": "dokumentasi_progress/abc.jpg", "jenis": "HASIL_KERJA" }
    ]
  },
  "workorder": {
    "id": 1,
    "progres_persen": 12,
    "status": { "id": 7, "kode": "IN_PROGRESS", "nama": "In Progress" }
  }
}
```

### Errors
| HTTP | Trigger |
|---|---|
| 403 | Login bukan member assignment WO |
| 422 | Status WO bukan `DITUGASKAN_KE_STAFF`/`IN_PROGRESS` |

---

## 6. Workflow: Staff Submit Progress (PROGRESS / SELESAI)

**Endpoint**: `POST /api/v1/progress-workorder/submit` (juga `PUT`/`PATCH`)
**Role**: staff member assignment WO.

### Body PROGRESS (intermediate)
```json
{
  "workorder_id": 1,
  "tipe_progress_kode": "PROGRESS",
  "hasil_pengerjaan": "Repair clamp terpasang segmen 1",
  "latitude": -7.250460,
  "longitude": 112.768830,
  "accuracy": 9.1
}
```
Plus optional `foto[]` di multipart.

### Body SELESAI (final)
```json
{
  "workorder_id": 1,
  "tipe_progress_kode": "SELESAI",
  "hasil_pengerjaan": "Semua sambungan terpasang, tes tekanan OK",
  "latitude": -7.250460,
  "longitude": 112.768830,
  "accuracy": 9.1
}
```

### Validation
| Field | Rule |
|---|---|
| `workorder_id` | required, exists |
| `tipe_progress_kode` | required (alias `tipe_progress` juga diterima), in:`PROGRESS`,`SELESAI` |
| `hasil_pengerjaan` | required, string, max 255 |
| `latitude`, `longitude` | required, numeric |
| `accuracy` | nullable, numeric |
| `foto[].*` | nullable, image jpeg/png/jpg, max 2048KB |

### Aturan khusus `tipe_progress_kode=SELESAI`
**Hanya boleh dikirim oleh user yang:**
1. `is_pic = true` di assignment (`peran=koordinator` saat di-assign), DAN
2. `pegawai.jabatan.kode = 'SENIOR_STAFF'`

Kalau salah satu tidak match → 403 `"Hanya PIC dengan jabatan Senior Staff yang dapat submit SELESAI"`.

### Quota harian (HANYA untuk `PROGRESS`, tidak untuk SELESAI)
- Max **8 submit per hari** per WO
- Max total = `(estimasi_selesai - tanggal_mulai + 1) * 8`
- Cek sisa via endpoint quota di bagian 7

### Side effects (PROGRESS)
- INSERT `progress_workorder` (`tipe_progress=PROGRESS`, `status=SUBMITTED`, `order++`)
- INSERT `dokumentasi_progress` per foto
- `workorder.status_id` → tetap `IN_PROGRESS`
- INSERT `workorder_action` `kode=SUBMIT_PROGRESS`

### Side effects (SELESAI)
- Sama seperti PROGRESS, plus:
- `workorder.status_id` → **`PENGECEKAN`**
- `workorder.tanggal_selesai` → `now()`
- INSERT `workorder_action` `kode=SELESAI_KERJA`

### Errors
| HTTP | Trigger |
|---|---|
| 403 | SELESAI tapi pengirim bukan PIC Senior Staff / bukan member WO |
| 422 | Quota harian habis (8/hari) atau kuota total habis |

---

## 7. Quota check

**Endpoint**: `GET /api/v1/progress-workorder/quota/{workorder_id}`

**Response**
```json
{
  "max_per_hari": 8,
  "submitted_hari_ini": 3,
  "sisa_hari_ini": 5,
  "max_total": 16,
  "submitted_total": 7,
  "sisa_total": 9,
  "total_hari": 2
}
```

Gunakan ini untuk disable tombol "Submit Progress" sebelum user kirim.

---

## 8. Progress detail history (review siklus)

Satu `progress_workorder` bisa punya banyak `progress_detail` (initial submit + resubmission setelah revisi).

### 8.1 List
`GET /api/v1/progress-detail?progress_workorder_id={id}`

### 8.2 Show
`GET /api/v1/progress-detail/{id}`

### 8.3 Resubmit setelah revisi
`POST /api/v1/progress-detail/resubmit`

Untuk staff yang mau resubmit progress yang sebelumnya kena `REVISI_REQUESTED`.

---

## 9. Workflow: SPV Review Progress (Approve / Revisi / Tolak)

**Endpoint**: `POST /api/v1/progress-workorder/review`
**Role**: SPV (`workorder.assigned_to`)
**Pre-condition**: WO status `PENGECEKAN` (untuk accept/tolak SELESAI) atau `IN_PROGRESS` (untuk revisi PROGRESS biasa).

### 9.1 Accept (final approve)
```json
{
  "progress_id": 4,
  "decision": "accept",
  "approval_notes": "Hasil pekerjaan sesuai standar, tes tekanan terdokumentasi."
}
```

**Side effects (auto, semua dalam 1 transaction)**
- INSERT `progress_detail` (`status=approved`, `reviewed_by_user_id`, `reviewed_at`)
- `progress.status_id` → `VERIFIED`
- `workorder.status_id` → **`SELESAI`**
- `workorder.approved_by_user_id`, `approved_at`, `approval_notes`, `tanggal_selesai` di-set
- **INSERT `laporan_workorder`** otomatis:
  - `nomor_laporan`: `LAP-WO-{YYYY}-{wo_id:0000}` (mis. `LAP-WO-2026-0001`)
  - `hasil_akhir_snapshot`: JSON snapshot row kategori (wo_meter/jaringan/infrastruktur)
  - `petugas_snapshot`: JSON array `[{user_id, nama, nip}]`
  - `catatan_spv`: dari `approval_notes`
- INSERT `workorder_action` `kode=APPROVE`

### 9.2 Revisi (minta perbaikan, balik ke staff)
```json
{
  "progress_id": 4,
  "decision": "revisi",
  "alasan_penolakan": "Foto hasil akhir belum jelas",
  "field_to_revise": ["photo", "description"]
}
```

**`field_to_revise`** adalah array of string yang menandakan field yang perlu direvisi staff. Disimpan sebagai comma-separated di `progress_detail.field_to_revise`.

**Side effects**
- INSERT `progress_detail` (`status=rejected`, `alasan_penolakan`, `field_to_revise`)
- `progress.status_id` → `REVISI_REQUESTED`
- INSERT progress baru (`tipe=REVISI`)
- `workorder.status_id` → `IN_PROGRESS` (loop balik ke Step 6)

### 9.3 Tolak (final reject)
```json
{
  "progress_id": 4,
  "decision": "tolak",
  "alasan_penolakan": "Hasil pekerjaan tidak sesuai spesifikasi WO"
}
```

**Side effects**
- INSERT `progress_detail` (`status=rejected`, `alasan_penolakan`)
- INSERT progress baru (`tipe=DITOLAK`)
- `workorder.status_id` → `DITOLAK_SPV`
- INSERT `workorder_action` `kode=REJECT` (`keterangan = alasan_penolakan`)

### Validation
| Field | Rule |
|---|---|
| `progress_id` | required, exists in `progress_workorder` |
| `decision` | required, in:`accept`,`revisi`,`tolak` |
| `approval_notes` | nullable, string (dipakai saat `accept`) |
| `alasan_penolakan` | nullable, string (dipakai saat `revisi`/`tolak`) |
| `field_to_revise` | nullable, array |

### Errors
| HTTP | Trigger |
|---|---|
| 403 | Login bukan SPV `assigned_to` |
| 422 | Status WO ≠ `PENGECEKAN`/`IN_PROGRESS` |

---

## 10. Workflow: Staff Cancel Progress

**Endpoint**: `POST /api/v1/progress-workorder/{progress_id}/cancel`
**Role**: staff yang sebelumnya men-submit progress tersebut (`submitted_by_user_id == auth.id`).
**Body**: kosong.

Buat membatalkan progress yang baru di-submit (mis. salah upload).
Side effect: `progress.status_id` → `DIBATALKAN`.

---

## 11. Workorder Action (audit trail)

**Endpoint**: `GET /api/v1/workorder-action?workorder_id={id}`

Returns semua action yang pernah terjadi di WO untuk ditampilkan sebagai timeline:

```json
[
  { "kode": "PENUGASAN",       "actor": "Illiyyin (SPV)",    "waktu_mulai": "..." },
  { "kode": "MULAI_KERJA",     "actor": "David (Senior)",     "waktu_mulai": "..." },
  { "kode": "SUBMIT_PROGRESS", "actor": "David (Senior)",     "waktu_mulai": "..." },
  { "kode": "SELESAI_KERJA",   "actor": "David (Senior)",     "waktu_mulai": "..." },
  { "kode": "APPROVE",         "actor": "Illiyyin (SPV)",    "keterangan": "Hasil pekerjaan sesuai standar." }
]
```

**Daftar `kode` action:**
| Kode | Deskripsi | Aktor |
|---|---|---|
| `PENUGASAN` | SPV assign staff | SPV |
| `FREEZE` | WO di-freeze | SPV/Manager |
| `RESUME` | WO dilanjutkan | SPV/Manager |
| `EXTEND` | Perpanjangan waktu | SPV/Manager |
| `MULAI_KERJA` | Staff start kerja | Staff |
| `SUBMIT_PROGRESS` | Staff submit progress | Staff |
| `SELESAI_KERJA` | Staff submit SELESAI | PIC Senior Staff |
| `APPROVE` | SPV approve final | SPV |
| `REJECT` | SPV tolak final | SPV |

---

## 12. Laporan Workorder

**Endpoint list**: `GET /api/v1/laporan-workorder`
**Endpoint detail**: `GET /api/v1/laporan-workorder/{id}`

Laporan **auto-generated** saat SPV approve (lihat 9.1). FE tidak perlu trigger generate.

**Response shape**
```json
{
  "id": 1,
  "workorder_id": 1,
  "nomor_laporan": "LAP-WO-2026-0001",
  "tanggal_terbit": "2026-05-25T11:30:00Z",
  "ringkasan_pekerjaan": "...",
  "hasil_akhir_snapshot": {
    "jenis_pipa": "PVC",
    "diameter_pipa": "150.00",
    "panjang_pipa": "12.50",
    "tingkat_kerusakan": "sedang",
    "tindakan_perbaikan": "...",
    "hasil_inspeksi": null
  },
  "petugas_snapshot": [
    { "user_id": 5, "nama": "David", "nip": "1234567894" },
    { "user_id": 6, "nama": "Budi",  "nip": "1234567895" }
  ],
  "catatan_spv": "Hasil pekerjaan sesuai standar.",
  "issued_by_user_id": 3,
  "approved_by_user_id": 3,
  "approved_at": "2026-05-25T11:30:00Z"
}
```

---

## 13. Status code dictionary

Selalu gunakan `status.kode` (string) untuk logika FE, **jangan** `status.id`. Id bisa bergeser kalau seeder berubah; kode stabil.

| `kode` | `nama` | Konteks |
|---|---|---|
| `BELUM_DISETUJUI` | Belum disetujui | (legacy) |
| `DISETUJUI` | Disetujui | (legacy) |
| `REVISI` | Revisi | (legacy progress lama) |
| `DITOLAK` | Ditolak | (legacy) |
| `PENGECEKAN` | Pengecekan | WO menunggu SPV review setelah SELESAI |
| `SELESAI` | Selesai | WO final (laporan terbit) |
| `IN_PROGRESS` | In Progress | Staff sedang mengerjakan |
| `FREEZE` | Freeze | WO ditunda |
| `DRAFT` | Draft | Progress dibuat saat assign, belum disubmit |
| `SUBMITTED` | Submitted | Progress sudah disubmit petugas |
| `VERIFIED` | Verified | Progress sudah diapprove SPV |
| `DITUGASKAN_KE_SPV` | Ditugaskan ke SPV | WO baru dibuat superadmin |
| `DITUGASKAN_KE_STAFF` | Ditugaskan ke Staff | SPV sudah assign tim |
| `REVISI_REQUESTED` | Revisi Requested | SPV minta perbaikan progres |
| `DITOLAK_SPV` | Ditolak SPV | Progress/WO ditolak SPV |
| `DIBATALKAN` | Dibatalkan | Progress dibatalkan petugas |

**Tipe progress** (`tipe_progress.kode`):
| Kode | Konteks |
|---|---|
| `MULAI` | Entry pertama saat staff start kerja |
| `PROGRESS` | Submit progress reguler |
| `SELESAI` | Submit final (PIC Senior Staff only) |
| `REVISI` | Auto-created saat SPV pilih revisi |
| `DITOLAK` | Auto-created saat SPV pilih tolak |

**`prioritas` workorder** (string enum di kolom):
`rendah` | `sedang` | `tinggi` | `darurat`

---

## 14. Material (peminjaman/pengembalian)

Untuk staff lapangan yang pinjam material per WO.

### 14.1 List peminjaman untuk WO
`GET /api/v1/workorder/{workorder_id}/peminjaman-material`

### 14.2 Pinjam material
`POST /api/v1/workorder/{workorder_id}/peminjaman-material`
```json
{
  "kode_material": "MAT-001",
  "jumlah": 5,
  "keterangan": "Untuk repair clamp 150mm"
}
```

### 14.3 Kembalikan material
`POST /api/v1/peminjaman-material/{peminjaman_id}/kembalikan`
```json
{
  "jumlah_kembali": 3,
  "kondisi": "baik",
  "keterangan": "2 unit sudah terpasang permanen"
}
```

### 14.4 List master material
`GET /api/v1/material`

---

## 15. Lembur SPL (Pengajuan Lembur)

### ⚠️ PENTING — `jenis_pekerjaan` adalah free-text, BUKAN dari endpoint `jenis-workorder`

**Jangan** panggil `GET /api/v1/jenis-workorder` untuk dropdown Jenis Pekerjaan.
Field ini adalah string bebas yang dikelola FE sepenuhnya (misal dropdown hardcoded).
Contoh nilai: `"Emergency Repairing"`, `"Routine Maintenance"`, `"Corrective Maintenance"`.

---

### 15.1 Submit pengajuan lembur — POST /api/v1/lembur-spl

**Role**: mobile user (SPV/petugas yang mengajukan)
**Auth**: Bearer token

**Body**

| Field             | Tipe              | Wajib | Keterangan |
|-------------------|-------------------|-------|------------|
| `judul_pekerjaan` | string (max 255)  | Ya    | |
| `jenis_pekerjaan` | string (max 255)  | Ya    | Free-text — FE kelola opsi sendiri, BUKAN dari `/jenis-workorder` |
| `tanggal_lembur`  | date `YYYY-MM-DD` | Ya    | |
| `jam_mulai`       | time `HH:MM`      | Tidak | Jam mulai lembur |
| `estimasi_jam`    | integer (1–24)    | Ya    | Durasi dalam jam |
| `alasan_lembur`   | string            | Ya    | |
| `members`         | array of int      | Ya (min 1) | User ID anggota tim — harus valid `users.id`, tidak boleh duplikat |

**Field yang JANGAN dikirim:**
- `pemohon_id` — auto-fill dari token auth
- `status_id` — auto-set ke `BELUM_DISETUJUI`

**Contoh request**
```json
{
  "judul_pekerjaan": "Perbaikan pompa shift malam",
  "jenis_pekerjaan": "Emergency Repairing",
  "tanggal_lembur": "2026-05-25",
  "jam_mulai": "16:00",
  "estimasi_jam": 2,
  "alasan_lembur": "Kerusakan mendadak pompa di stasiun A.",
  "members": [3, 5, 8]
}
```

**Response 201**
```json
{
  "message": "Pengajuan lembur SPL berhasil dibuat",
  "data": {
    "id": 7,
    "pemohon_id": 12,
    "jenis_pekerjaan": "Emergency Repairing",
    "judul_pekerjaan": "Perbaikan pompa shift malam",
    "tanggal_lembur": "2026-05-25",
    "jam_mulai": "16:00:00",
    "estimasi_jam": 2,
    "alasan_lembur": "Kerusakan mendadak pompa di stasiun A.",
    "status_id": 1,
    "waktu_pengajuan": "2026-05-25T09:00:00.000000Z",
    "status": { "id": 1, "kode": "BELUM_DISETUJUI", "nama": "Belum Disetujui" },
    "pemohon": { "id": 12, "pegawai": { "nama": "Illiyyin", "nip": "1234567832" } },
    "members": [
      { "id": 1, "lembur_spl_id": 7, "user_id": 3 }
    ]
  }
}
```

---

### 15.2 List — GET /api/v1/lembur-spl

Mengembalikan semua record dengan relasi: `pemohon`, `verifikator`, `status`, `members`.

### 15.3 Detail — GET /api/v1/lembur-spl/{id}

Sama seperti list, untuk satu record.

### 15.4 Approval (web FE / superadmin) — PUT /api/v1/lembur-spl/{id}

```json
{
  "verifikator_id": 1,
  "status_id": 2,
  "alasan_ditolak": null
}
```

`status_id`: `1`=BELUM_DISETUJUI, `2`=DISETUJUI, `4`=DITOLAK. Ini bagian web FE, bukan mobile.

---

## 16. Mobile-specific gotchas

### 16.1 Multipart vs JSON
- `start` dan `submit` endpoint menerima **JSON tanpa foto** atau **multipart dengan foto**.
- Untuk multipart, key foto harus bracket-array: `foto[]` (Flutter `MultipartFile.fromPath('foto[]', ...)`).
- `Content-Type` di multipart **jangan** di-set manual — biarkan HTTP client Flutter set boundary sendiri.

### 16.2 Hydration trick di controller
[ProgressWorkorderController.php:35-71](app/Http/Controllers/ProgressWorkorderController.php#L35-L71) — `hydrateInputFromBody` mendekode body manual untuk client lama yang kirim multipart tapi method GET/PUT dengan parsing yang quirky. Selama Flutter pakai `POST` dengan content-type yang benar, FE tidak perlu pikirkan ini.

### 16.3 Geolocation accuracy
Field `accuracy` (meter) optional tapi **disarankan dikirim**. BE menyimpan untuk audit. Mobile pakai `Geolocator.getCurrentPosition(desiredAccuracy: LocationAccuracy.high)` lalu kirim `accuracy: position.accuracy`.

### 16.4 Geofence (radius_meter)
Saat assign, BE auto-create `m_location` dengan `radius_meter=100`. FE bisa fetch `workorder.workorder_assignment.location` untuk render circle radius di map dan validate lokal sebelum submit (tapi BE tidak enforce geofence sekarang — opsional di FE saja).

### 16.5 Token storage
Token Sanctum **non-expiring** by default. Simpan di `flutter_secure_storage`. Hapus via `/auth/logout` saat user sign-out.

### 16.6 Image URL
`dokumentasi_progress.url` adalah path relatif (`dokumentasi_progress/abc.jpg`). FE compose URL: `{host}/storage/{url}` (Laravel public disk).

### 16.7 Date/time format
- `tanggal_*` field: `YYYY-MM-DD` (ISO date)
- `waktu_*`, `*_at` field: ISO 8601 with timezone (`2026-05-25T07:15:00Z`)
- Kirim ke BE: gunakan `DateTime.toIso8601String()`, atau untuk date-only `DateFormat('yyyy-MM-dd').format(date)`.

### 16.8 Role-based UI gating
| Role | Mobile screens yang relevan |
|---|---|
| superadmin | (biasanya tidak pakai mobile — pakai web dashboard) |
| manager | (biasanya tidak pakai mobile) |
| employee + jabatan SPV | List WO yang `assigned_to=me`, Assign staff, Review progress |
| employee + jabatan Senior Staff | List WO dimana saya member, Start/Submit (termasuk SELESAI) |
| employee + jabatan Staff | List WO dimana saya member, Start/Submit (PROGRESS only) |

FE check role/jabatan via `auth/me` lalu show/hide tombol sesuai matrix di atas.

---

## 17. Status transition diagram (untuk FE state machine)

```
[Superadmin]
   ↓ create WO
DITUGASKAN_KE_SPV
   ↓ SPV assign staff
DITUGASKAN_KE_STAFF
   ↓ staff /progress-workorder/start
IN_PROGRESS
   ├─ staff submit PROGRESS  ──→ IN_PROGRESS (loop)
   └─ PIC Senior submit SELESAI
        ↓
      PENGECEKAN
        ├─ SPV review accept  ──→ SELESAI ✅  (laporan_workorder terbit)
        ├─ SPV review revisi  ──→ IN_PROGRESS (loop, staff perbaiki)
        └─ SPV review tolak   ──→ DITOLAK_SPV ❌
```

---

## 18. Smoke-test sequence (untuk regression / FE QA)

| # | Auth | Method | URL | Expected status |
|---|---|---|---|---|
| 1 | — | POST | `/auth/login` (superadmin) | 200 + token |
| 2 | superadmin | POST | `/workorder` | 201, WO.status=DITUGASKAN_KE_SPV |
| 3 | — | POST | `/auth/login` (iyyin SPV) | 200 + token |
| 4 | SPV | POST | `/workorder/{id}/assign-staff` | 200, WO.status=DITUGASKAN_KE_STAFF |
| 5 | — | POST | `/auth/login` (david Senior Staff) | 200 |
| 6 | staff | POST | `/progress-workorder/start` | 201, WO.status=IN_PROGRESS |
| 7 | staff | POST | `/progress-workorder/submit` (PROGRESS) | 201 |
| 8 | staff | POST | `/progress-workorder/submit` (SELESAI) | 201, WO.status=PENGECEKAN |
| 9 | — | POST | `/auth/login` (iyyin SPV) | 200 |
| 10 | SPV | POST | `/progress-workorder/review` (accept) | 200, WO.status=SELESAI |
| 11 | SPV | GET | `/laporan-workorder` | 200, terdapat baris nomor LAP-WO-2026-{id} |
| 12 | SPV | GET | `/workorder-action?workorder_id={id}` | 200, ada 5 entry (PENUGASAN, MULAI_KERJA, SUBMIT_PROGRESS, SELESAI_KERJA, APPROVE) |

---

## 19. Catatan migrasi DB

Setelah pull repo, **wajib jalankan**:
```bash
php artisan migrate --force
php artisan db:seed --force   # idempotent — aman dijalankan ulang
php artisan storage:link      # supaya /storage/dokumentasi_progress accessible
```

FE bisa rely on:
- `m_status`: 16 row kanonik (lihat dictionary di Bagian 13)
- `m_action`: 9 row kanonik (lihat tabel di Bagian 11)
- `m_tipe_progress`: 5 row (MULAI, PROGRESS, SELESAI, REVISI, DITOLAK)
- `m_jabatan`: punya kolom `kode` (`SPV`, `SENIOR_STAFF`, `STAFF`, dst.)
- `m_jenis_workorder`: punya kolom `kategori_form` (`meter`/`jaringan`/`infrastruktur`)

---

## 20. Endpoint yang DIHAPUS (jangan dipanggil dari FE)

Endpoint berikut **sudah dihapus** dari routes. Kalau FE versi lama masih nge-hit → 404, perlu di-update:

| Old endpoint | Replacement |
|---|---|
| `POST /api/v1/workorder/{id}/approve` | `POST /api/v1/progress-workorder/review` dengan `decision: "accept"` |
| `POST /api/v1/workorder/{id}/reject` | `POST /api/v1/progress-workorder/review` dengan `decision: "tolak"` |

Alasan: dua-step approval (SPV → Manager) tidak dipakai. SPV approve langsung jadi final, dan laporan auto-terbit.

---

## Changelog dokumen ini

- **2026-05-24**: Initial draft, dibuat setelah testing API E2E lengkap (Create WO → SPV assign → Staff start/submit → SPV approve → laporan terbit). Manager approval flow dihapus dari kontrak.
