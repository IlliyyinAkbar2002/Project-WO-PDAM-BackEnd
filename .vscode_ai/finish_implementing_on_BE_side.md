# BE Implementation Summary: Material Borrowing & Return

Dokumen ini ditujukan untuk FE agent agar dapat menyinkronkan implementasi frontend dengan backend yang sudah selesai.

---

## Base URL

```
/api/v1
```

Semua endpoint memerlukan header:
```
Authorization: Bearer {sanctum_token}
```

---

## Endpoints

### 1. List Peminjaman per WO

```
GET /workorder/{workorder_id}/peminjaman-material
```

Response `200`:
```json
{
  "message": "Data peminjaman material berhasil diambil.",
  "data": [
    {
      "id": 1,
      "workorder_id": 1,
      "material_kode": 1001,
      "jumlah_pinjam": 5,
      "catatan": "Pemasangan pipa Jl. Test",
      "diajukan_oleh": 6,
      "diajukan_at": "2026-05-27T14:00:00.000000Z",
      "status": "DIPINJAM",
      "jumlah_kembali": null,
      "kondisi_kembali": null,
      "dikembalikan_at": null,
      "diverifikasi_oleh": null,
      "diverifikasi_at": null,
      "catatan_verifikator": null,
      "material": { "kode_material": 1001, "nama": "Pipa HDPE DN-100 PN-10", "satuan": "meter" },
      "pengaju": { "id": 6, "name": "...", "pegawai": { "id": ..., "nama": "Budi", "nip": "..." } },
      "verifier": null
    }
  ]
}
```

---

### 2. Ajukan Peminjaman (Staff)

```
POST /workorder/{workorder_id}/peminjaman-material
```

**Siapa yang bisa:** Anggota tim WO (member di `wo_assignment_member`).

**Kapan boleh:** Status WO harus `DITUGASKAN_KE_STAFF` atau `IN_PROGRESS`.

Request body:
```json
{
  "material_kode": 1001,
  "jumlah_pinjam": 5,
  "catatan": "Opsional — keterangan kebutuhan"
}
```

Response `201`:
```json
{
  "message": "Peminjaman material berhasil diproses dan stok dikurangi.",
  "data": {
    "id": 1,
    "workorder_id": 1,
    "material_kode": 1001,
    "jumlah_pinjam": 5,
    "status": "DIPINJAM",
    "diajukan_at": "2026-05-27T14:00:00.000000Z",
    "material": { "kode_material": 1001, "nama": "Pipa HDPE DN-100 PN-10", "satuan": "meter" }
  }
}
```

Error responses:
| HTTP | Kondisi |
|------|---------|
| 400  | Stok tidak mencukupi — response menyertakan pesan dengan jumlah tersedia |
| 400  | Status WO tidak valid untuk peminjaman |
| 403  | User bukan anggota tim WO |
| 404  | WO tidak ditemukan |
| 422  | Validasi gagal (field wajib kosong / `material_kode` tidak ada di master) |

---

### 3. Ajukan Pengembalian (Staff)

```
POST /peminjaman-material/{peminjaman_id}/kembalikan
```

**Siapa yang bisa:** Anggota tim WO yang sama.

**Efek:** Status berubah ke `PENDING_KEMBALI`. Stok **belum** dikembalikan — menunggu approval SPV.

Request body:
```json
{
  "jumlah_kembali": 2,
  "kondisi_kembali": "Dipasang 3 meter di lapangan, sisa 2 meter kondisi baik"
}
```

> `jumlah_kembali` boleh 0 (semua material habis terpakai). Tidak boleh melebihi `jumlah_pinjam`.

Response `200`:
```json
{
  "message": "Pengajuan pengembalian material berhasil dikirim dan menunggu persetujuan supervisor.",
  "data": {
    "id": 1,
    "status": "PENDING_KEMBALI",
    "jumlah_kembali": 2,
    "kondisi_kembali": "Dipasang 3 meter di lapangan, sisa 2 meter kondisi baik",
    ...
  }
}
```

Error responses:
| HTTP | Kondisi |
|------|---------|
| 400  | Status bukan `DIPINJAM` (sudah `PENDING_KEMBALI` atau `DIKEMBALIKAN`) |
| 400  | `jumlah_kembali` > `jumlah_pinjam` |
| 403  | User bukan anggota tim WO |
| 404  | Peminjaman tidak ditemukan |

**Notifikasi:** SPV WO otomatis menerima notifikasi database (`type: "material_return_submitted"`).

---

### 4. Verifikasi Pengembalian (SPV)

```
POST /peminjaman-material/{peminjaman_id}/verify
```

**Siapa yang bisa:** Hanya SPV yang di-assign ke WO terkait (`workorder_assignment.spv_user_id`).

**Kapan boleh:** Status peminjaman harus `PENDING_KEMBALI`.

Request body:
```json
{
  "status": "APPROVED",
  "catatan_verifikator": "Opsional — catatan SPV"
}
```

> `status` harus `"APPROVED"` atau `"REJECTED"`.

Response `200`:
```json
{
  "message": "Status pengembalian berhasil diperbarui menjadi APPROVED.",
  "data": {
    "id": 1,
    "status": "DIKEMBALIKAN",
    "diverifikasi_oleh": 3,
    "diverifikasi_at": "2026-05-27T15:00:00.000000Z",
    "catatan_verifikator": "Sesuai dokumentasi",
    ...
  }
}
```

**Efek per status:**

| `status` input | Status peminjaman | Efek stok |
|---|---|---|
| `APPROVED` | `DIKEMBALIKAN` | `m_material.terpakai` berkurang sebesar `jumlah_kembali` (sisa yang dikembalikan fisik). Material yang habis terpakai di lapangan dianggap dikonsumsi permanen. |
| `REJECTED` | `DIPINJAM` | Stok tidak berubah. `jumlah_kembali` dan `kondisi_kembali` di-reset ke `null`. Staff harus submit ulang. |

Error responses:
| HTTP | Kondisi |
|------|---------|
| 400  | Status bukan `PENDING_KEMBALI` |
| 403  | User bukan SPV WO terkait |
| 404  | Peminjaman tidak ditemukan |

**Notifikasi:** Staff pengaju otomatis menerima notifikasi database (`type: "material_return_verified"`).

---

## Status Lifecycle Peminjaman

```
DIPINJAM
  │
  ├─ Staff submit kembalikan ──► PENDING_KEMBALI
  │                                    │
  │                          SPV APPROVED ──► DIKEMBALIKAN (final)
  │                          SPV REJECTED ──► DIPINJAM (kembali ke awal)
  │
  └─ (tidak ada pengembalian — material habis terpakai semua)
```

---

## Master Material (`GET /material`)

20 material kanonik sudah di-seed. Gunakan endpoint ini untuk dropdown pemilihan material di form pinjam.

Response item:
```json
{
  "kode_material": 1001,
  "nama": "Pipa HDPE DN-100 PN-10",
  "jumlah_stok": 500,
  "terpakai": 0,
  "satuan": "meter",
  "kategori": "Perpipaan",
  "tersedia": 500
}
```

Field `tersedia` = `jumlah_stok - terpakai` (computed, bukan kolom DB). Tampilkan ini di UI agar staff tahu stok yang bisa dipinjam.

Kategori yang ada: `Perpipaan`, `Fitting`, `SR`, `Bahan Kimia`.

---

## Catatan Integrasi FE

1. **Field name:** Gunakan `material_kode` (bukan `material_id`) saat kirim payload ke BE.
2. **Otorisasi UI:** Tombol "Pinjam Material" hanya tampil jika status WO = `DITUGASKAN_KE_STAFF` atau `IN_PROGRESS`. Tombol "Kembalikan" hanya tampil jika status peminjaman = `DIPINJAM`. Tombol "Verify" hanya tampil untuk user SPV WO terkait dan status = `PENDING_KEMBALI`.
3. **Peminjaman bersifat opsional** — tidak semua WO memerlukan material.
4. **Notifikasi:** Gunakan `GET /notifications` untuk polling/display notifikasi. Field `data.type` untuk membedakan jenis notifikasi: `material_return_submitted` (untuk SPV) dan `material_return_verified` (untuk Staff).
5. **Stok real-time:** Fetch ulang `GET /material/{kode_material}` atau `GET /material` setelah pinjam/verify untuk menampilkan stok terkini.
