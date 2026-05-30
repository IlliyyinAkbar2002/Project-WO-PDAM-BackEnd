Berdasarkan kondisi BE saat ini ada dua jalur tangkapan payload yang perlu dibedakan: yang sudah ada di kode vs yang dirancang di dokumen flow. Penting karena keduanya memberi jawaban berbeda untuk pertanyaan "kapan FE harus kirim".

## 1. Jalur yang sudah aktif sekarang: `POST /api/v1/laporan-workorder`

Route (`routes/api.php` baris 81), di belakang `auth:sanctum`:

```
POST /api/v1/laporan-workorder
```

`LaporanWorkorderController@store` melakukan `$request->validate(...)` lalu langsung `LaporanWorkorder::create($validatedData)`. Jadi payload ditangkap dari **JSON body request**, divalidasi, terus disimpan apa adanya. Tidak ada transformasi, tidak ada listener, tidak ada hook ke status workorder.

Bentuk payload yang diharapkan endpoint ini (mengikuti `validate()` di controller):

```json
{
  "workorder_id": 123,
  "nomor_laporan": "LAP-WO-2026-0001",
  "tanggal_terbit": "2026-05-21T10:00:00Z",
  "ringkasan_pekerjaan": "string narasi final",
  "hasil_akhir_snapshot": { "...": "row wo_{kategori}" },
  "petugas_snapshot": [
    { "user_id": 5, "nama": "Budi", "nip": "123" }
  ],
  "catatan_spv": "opsional",
  "issued_by_user_id": 7,
  "approved_by_user_id": 7
}
```

Catatan teknis di kode saat ini:
- Nilai JSON di-cast otomatis ke array via `protected $casts` di model (`hasil_akhir_snapshot`, `petugas_snapshot` → `array`; `tanggal_terbit`, `approved_at` → `datetime`). Jadi FE kirim object/array biasa, BE handle serialisasi ke JSONB.
- `nomor_laporan` divalidasi `unique:laporan_workorder,nomor_laporan` — kalau FE generate sendiri, FE harus pastikan unik (rawan race).
- `approved_by_user_id` di validator `nullable`, **tapi** kolomnya di migration NOT NULL (`->constrained('users')`). Insert dengan nilai null akan ditolak DB. Sama untuk `approved_at` yang NOT NULL di migration tapi tidak ada di validator sama sekali — kalau FE tidak kirim, insert gagal.
- Endpoint hanya `index | show | store` — belum ada `update` / `destroy`.

Artinya: kalau FE mau pakai endpoint ini, FE harus tahu nomor laporan, snapshot, semua FK user, dan timing terbit. Itu bukan tanggung jawab natural FE.

## 2. Jalur yang dirancang di dokumen flow: auto-generate by BE

`Flow_WO.md` Tahap 11 dan `ERD_Logical.md` BR-5 jelas menyatakan laporan **bukan dikirim FE**. Trigger-nya adalah event Laravel `WorkorderApproved` → listener `IssueWorkorderReport` yang dijalankan saat `workorder.status` berubah ke `SELESAI` (setelah SPV approve final). Listener melakukan:

1. Generate `nomor_laporan` via `NomorLaporanGenerator` (`LAP-WO-YYYY-NNNN`).
2. Snapshot row `wo_{kategori}` ke `hasil_akhir_snapshot`.
3. Snapshot daftar petugas ke `petugas_snapshot`.
4. Insert row `laporan_workorder`.
5. Dispatch job render PDF.

Status implementasi saat ini: **belum ada**. Tidak ada `IssueWorkorderReport`, `NomorLaporanGenerator`, atau job PDF di `app/`. Endpoint `store` yang ada sekarang adalah jalur manual yang menyalahi desain.

## Rekomendasi untuk FE timing

- Kalau target sesuai desain: FE **tidak perlu kirim payload laporan sama sekali**. Setelah SPV approve final via endpoint approval workorder, BE harus auto-terbit laporan, dan FE cukup `GET /api/v1/laporan-workorder/{id}` atau `GET /api/v1/laporan-workorder?workorder_id=X` untuk menampilkan hasilnya. Listener-nya yang masih perlu diimplementasi di BE (ticket TKT-15 di `Implement_this_ticketing.md`).
- Kalau sementara mau pakai endpoint manual yang ada: FE kirim `POST /api/v1/laporan-workorder` segera setelah dapat respons sukses dari endpoint approve final SPV, dengan payload lengkap di atas. Tapi sebelum itu, perbaiki dulu mismatch validator vs migration (`approved_by_user_id` dan `approved_at`) supaya insert tidak gagal di DB.

Mau saya implementasikan listener `IssueWorkorderReport` + `NomorLaporanGenerator` supaya FE tidak perlu pegang payload laporan sama sekali, atau cukup perbaiki validator+kolom NOT NULL agar endpoint manual yang sekarang bisa dipakai dulu?