# Migration Notes — Mei 2026

> Ringkasan perubahan backend yang **breaking** untuk FE (Next.js Web + Flutter Mobile).
> Dokumen ini dibuat sebagai brief untuk tim FE.

---

## Ringkasan

1. Tabel DB legacy pola EAV (Entity-Attribute-Value) untuk form WO **DI-DROP**:
   - `form_workorder`
   - `detail_form`
   - `detail_progress`
2. Tabel baru **DITAMBAH**: `workorder_assignment` (1:1 ke `workorder`) — mencatat event "SPV melakukan assign" secara eksplisit.
3. Lifecycle WO **diringkas**: tahap "Manager approve final" DIHAPUS. SPV jadi approver terakhir.

---

## 1. Endpoint API yang DIHAPUS (Breaking)

FE harus **stop memanggil** endpoint berikut — sekarang return 404 (tidak ada rute):

| Method | Endpoint lama | Status |
|---|---|---|
| GET/POST/PUT/DELETE | `/api/v1/detail-progress` | **DROPPED** |
| GET/POST/PUT/DELETE | `/api/v1/jenis-workorder/{id}/form-workorder` | **DROPPED** |
| GET/POST/PUT/DELETE | `/api/v1/jenis-workorder/{id}/form-workorder/{form_workorder_id}` | **DROPPED** |
| GET/POST/PUT/DELETE | `/api/v1/jenis-workorder/{id}/detail-form` | **DROPPED** |
| GET/POST/PUT/DELETE | `/api/v1/jenis-workorder/{id}/detail-form/{detail_form_id}` | **DROPPED** |
| GET/POST/PUT/DELETE | `/api/v1/jenis-workorder/{jwo}/form-workorder/{fwo}/detail-form/*` | **DROPPED** |

Endpoint `POST /api/v1/jenis-workorder` dan `PUT /api/v1/jenis-workorder/{id}` **response-nya berubah**:
- Request body lama punya field `form_workorder[]` + `form_workorder[].detail_form[]` — **sudah tidak diterima**.
- Request body baru: hanya `{ "nama": "...", "kategori_form": "meter" | "jaringan" | "infrastruktur" }`.
- Response lama punya key `form_workorder` — **sudah tidak ada**.
- Response baru: `{ id, nama, kategori_form }`.

---

## 2. Endpoint API yang DATANG (coming soon, belum diimplementasi di rev ini)

Untuk menggantikan fungsionalitas form dinamis:

| Method | Endpoint baru | Fungsi |
|---|---|---|
| POST | `/api/v1/workorder/{id}/assign-staff` | Sudah ada skeleton route — SPV submit form kategori + assign Staff dalam 1 request. Body: `{ "form_kategori": { ... field sesuai kategori }, "petugas": [{ "user_id", "peran" }], "catatan_spv"?: string }` |
| GET | `/api/v1/jenis-workorder/{id}/schema` | **Belum dibuat** — akan expose daftar field kategori untuk FE Mobile render form kosong. Ditunggu ticket berikutnya. |

---

## 3. Perubahan DB Schema — yang perlu diketahui FE

### Tabel baru: `workorder_assignment`

```
workorder_assignment
├── id              bigint PK
├── workorder_id    bigint UNIQUE FK → workorder.id (ON DELETE CASCADE)
├── spv_user_id     bigint FK → users.id
├── assigned_at     timestamp NOT NULL
├── catatan_spv     text NULL
└── created_at / updated_at
```

Relasi di response `GET /api/v1/workorder/{id}`: field baru `workorder_assignment` akan tersedia (kalau FE eager-load `with=workorderAssignment`). Skema payload:

```json
{
  "id": 101,
  "workorder_id": 42,
  "spv_user_id": 7,
  "assigned_at": "2026-05-04T08:30:00Z",
  "catatan_spv": "Tolong prioritaskan — pelanggan komplain",
  "created_at": "...",
  "updated_at": "..."
}
```

Field ini null kalau WO masih di status `DITUGASKAN_KE_SPV` (belum di-assign ke Staff). Begitu SPV klik "Tugaskan", row ini ter-insert.

### Tabel kategori (`wo_meter`, `wo_jaringan`, `wo_infrastruktur`)

Sudah di-create di migration sebelumnya (Apr 2026). Schema field lengkap ada di [.cursor/ERD_Physical.dbml](.cursor/ERD_Physical.dbml). Mobile FE harus render form-nya sesuai `jenis_workorder.kategori_form`:

| Kategori | Jenis WO yang masuk | Field wajib di-render |
|---|---|---|
| `meter` | Pemasangan Meter Baru, Pergantian Meter, Kalibrasi Meteran | nomor_meter, kondisi_meter_awal, (hasil_kalibrasi) |
| `jaringan` | Perbaikan Pipa Bocor, Pemasangan Pipa Baru, Pergantian Pipa Lama, Pembersihan Saluran | jenis_pipa, diameter_pipa, panjang_pipa, tingkat_kerusakan |
| `infrastruktur` | Pemeliharaan Pompa, Pemeliharaan Reservoir, Inspeksi Rutin Aset | nama_aset, jenis_aset, kapasitas, kondisi_awal |

---

## 4. Perubahan Flow Bisnis

Status WO yang **dihapus dari lifecycle aktif** (row `m_status` tetap ada di DB untuk kompatibilitas row lama, tapi nggak akan ter-trigger di flow baru):

- `MENUNGGU_APPROVAL_MANAGER`
- `DITOLAK_MANAGER`

Status terminal sukses baru: **langsung ke `SELESAI`** setelah SPV klik Approve di Tahap 10.

Diagram flow baru lihat [.cursor/Flow_WO.md](.cursor/Flow_WO.md) Section 2.

---

## 5. Field `progress_workorder` — yang perlu diketahui FE

Endpoint `PUT /api/v1/progress-workorder/{id}` (update progres):
- **Field lama yang DIHAPUS** dari request body & response:
  - `detail_progress[]` (array field EAV)
  - `detail_progress_images[]` (upload per-detail_form_id)
- Sisanya (`hasil_pengerjaan`, `waktu_submit`, `foto[]`) tetap sama.

Untuk tipe SELESAI, Staff juga harus **update field hasil akhir di tabel kategori** (`kondisi_meter_akhir`, `hasil_kalibrasi`, `tindakan_perbaikan`, dll). Endpoint terpisah untuk ini **belum dibuat** — akan disediakan di ticket berikutnya.

---

## 6. Checklist untuk FE Team

Di sisi Next.js (Web):
- [ ] Hapus semua pemanggilan ke `/api/v1/detail-progress`, `/api/v1/detail-form`, `/api/v1/jenis-workorder/*/form-workorder`
- [ ] Update form Create/Update Jenis Workorder — drop UI field dinamis, ganti jadi dropdown `kategori_form` (3 pilihan)
- [ ] Update response parser `jenis_workorder` — hapus pembacaan key `form_workorder` / `detail_form`
- [ ] (Opsional untuk capstone) Hapus menu Manager approve (WO tidak lagi muncul di antrian Manager)

Di sisi Flutter (Mobile):
- [ ] Tunggu endpoint `GET /api/v1/jenis-workorder/{id}/schema` siap (ticket berikutnya) untuk render form kategori
- [ ] Update screen "Submit Progress SELESAI" — drop section input dinamis `detail_progress`, ganti dengan field kategori sesuai WO (nomor_meter, hasil_kalibrasi, dsb.)
- [ ] Siapkan screen "SPV Isi Form + Assign" — consume endpoint `POST /api/v1/workorder/{id}/assign-staff` dengan body baru

---

## 7. File Reference

- ERD lengkap: [.cursor/ERD_Physical.dbml](../.cursor/ERD_Physical.dbml)
- Flow bisnis: [.cursor/Flow_WO.md](../.cursor/Flow_WO.md)
- API list aktif: [docs/API_QUICK_REFERENCE.md](API_QUICK_REFERENCE.md)
- Migrations:
  - `2026_05_04_100000_create_workorder_assignment_table.php`
  - `2026_05_04_100100_drop_legacy_form_tables.php`
