# Riwayat Implementasi Notifikasi (Backend) — Brief untuk FE Agent

Dokumen ini merangkum **apa yang sudah selesai diimplementasikan di Backend Laravel** untuk fitur notifikasi (Laravel Database Notification), beserta kontrak API yang harus diikuti oleh Frontend (Flutter).

> Dokumen rencana awal: `Implementation_notifaction.md`.
> Dokumen ini menggantikan bagian "Sisi Backend" dari rencana awal karena ada beberapa perbedaan kecil (lihat bagian **Perbedaan dari Rencana Awal**).

---

## 1. Konteks Singkat

- BE menggunakan **fitur bawaan Laravel Database Notification** (tabel `notifications` standar, kolom `data` JSON).
- Migrasi `notifications` sudah dijalankan oleh user.
- Notifikasi dikirim ke model `App\Models\User` (kolom `notifiable_type` = `"App\\Models\\User"`, `notifiable_id` = user.id).
- Tidak ada *foreign key* fisik dari `notifications` ke tabel lain — relasi dilakukan via payload `work_order_id`.

---

## 2. Endpoint API (kontrak final)

Semua endpoint berada di bawah prefix `/api/v1/` dan dilindungi middleware `auth:sanctum`.
Authentication header: `Authorization: Bearer <sanctum_token>`.

### 2.1 GET `/api/v1/notifications`

Mengambil seluruh notifikasi milik user yang sedang login (urut `created_at` DESC).

**Response 200 OK:**
```json
{
  "success": true,
  "message": "Notifications retrieved successfully",
  "data": [
    {
      "id": "7a94d80a-9d95-46ff-b631-c423ba4e6b18",
      "type": "App\\Notifications\\WorkOrderNotification",
      "data": {
        "title": "Tugas Work Order Baru",
        "message": "Superadmin membagikan WO #Perbaikan Pipa Bocor kepada Anda.",
        "work_order_id": 12,
        "type": "wo_created",
        "sender_name": "Superadmin"
      },
      "read_at": null,
      "created_at": "2026-05-27T12:00:00+00:00"
    }
  ]
}
```

Catatan:
- `id` adalah **UUID string** (bukan integer).
- `type` (luar) = class name notifikasi di Laravel — boleh diabaikan oleh FE.
- `data.type` (dalam) = jenis notifikasi yang relevan untuk UI (lihat bagian 4).
- `read_at` = `null` artinya belum dibaca; kalau sudah dibaca → ISO 8601 string.
- `created_at` selalu ISO 8601 string.

### 2.2 PUT `/api/v1/notifications/{id}/read`

Menandai notifikasi sebagai sudah dibaca. Method `POST` juga didukung untuk kompatibilitas.

**Path params:** `id` = UUID notifikasi (string).

**Response 200 OK:**
```json
{
  "success": true,
  "message": "Notification marked as read"
}
```

**Response 404 Not Found** (notifikasi bukan milik user atau tidak ada):
```json
{
  "success": false,
  "message": "Notification not found"
}
```

Behavior tambahan:
- Idempotent: kalau sudah `read_at != null`, endpoint tetap mengembalikan 200 tanpa mengubah apa-apa.
- Otorisasi: user hanya bisa menandai notifikasi miliknya sendiri.

---

## 3. Skema Payload `data` (kontrak FE)

Bentuk objek `data` di setiap notifikasi:

| Field | Tipe | Keterangan |
|---|---|---|
| `title` | `string` | Judul notifikasi (untuk header item di UI) |
| `message` | `string` | Pesan body |
| `work_order_id` | `int` | ID WO terkait — dipakai untuk navigasi ke detail WO |
| `type` | `string` | Jenis notifikasi (lihat tabel di bagian 4) |
| `sender_name` | `string` | Nama pengirim — dipakai untuk inisial avatar & label |

---

## 4. Tipe Notifikasi yang Dipancarkan BE

| `data.type` | Kapan Dipicu | Penerima | Contoh `message` |
|---|---|---|---|
| `wo_created` | Superadmin membuat WO baru dan menugaskannya ke SPV | SPV (`workorder.assigned_to`) | `"Superadmin membagikan WO #<nama_workorder> kepada Anda."` |
| `wo_assigned` | SPV mengisi form kategori dan assign Staff | Setiap Staff yang di-assign | `"<Nama SPV> menugaskan Anda pada WO #<nama_workorder>."` |
| `wo_ready_for_review` | PIC (Senior Staff) submit progres `SELESAI` → WO masuk status `PENGECEKAN` | SPV (`workorder.assigned_to`) | `"<Nama PIC> telah menyelesaikan WO #<nama_workorder> dan menunggu review Anda."` |
| `wo_completed` | SPV approve progres `SELESAI` → WO masuk status `SELESAI` | Pembuat WO / Superadmin (`workorder.created_by_user_id`) | `"<Nama SPV> telah menyelesaikan WO #<nama_workorder>."` |

> FE perlu menambahkan `wo_ready_for_review` ke union type — tipe ini **belum ada** di rencana awal (`Implementation_notifaction.md`).

Behavior umum trigger:
- Semua notifikasi dikirim **best-effort**: kalau gagal kirim (mis. user tidak ditemukan, error driver), transaksi WO tetap sukses — error hanya di-log via `Log::warning(...)`.
- `sender_name` di-resolve dari `pegawai.nama` → fallback `users.name` → fallback default (`'Superadmin'` / `'SPV'` / `'Petugas'`).
- `#<nama_workorder>` saat ini dipakai sebagai referensi WO di pesan. Belum ada field "kode WO" terpisah di tabel `workorder`.

---

## 5. Daftar File BE yang Dibuat/Diubah

### Dibuat
- `app/Notifications/WorkOrderNotification.php` — class notifikasi tunggal (channel `database`) untuk seluruh tipe notifikasi WO.
- `app/Http/Controllers/NotificationController.php` — controller untuk endpoint index & markAsRead.

### Diubah
- `routes/api.php` — registrasi 2 route notifikasi di bawah `auth:sanctum`.
- `app/Services/WorkorderService.php` — kirim `wo_created` saat WO dibuat.
- `app/Services/AssignmentService.php` — kirim `wo_assigned` ke seluruh staff saat assign.
- `app/Http/Controllers/ProgressWorkorderController.php` — kirim `wo_ready_for_review` saat PIC submit SELESAI.
- `app/Http/Controllers/ProgressDetailController.php` — kirim `wo_completed` saat SPV approve progres SELESAI.

---

## 6. Perbedaan dari Rencana Awal (`Implementation_notifaction.md`)

| Aspek | Rencana Awal | Yang Diimplementasikan |
|---|---|---|
| URL prefix | `/api/notifications` & `/api/notifications/{id}/read` | `/api/v1/notifications` & `/api/v1/notifications/{id}/read` — mengikuti konvensi project (semua route di-prefix `v1`). |
| Tipe notifikasi | `wo_created`, `wo_assigned`, `wo_completed` | Ditambah `wo_ready_for_review` (atas permintaan, untuk notifikasi "siap di-review" ke SPV). |

Selain dua hal di atas, kontrak payload dan struktur response sesuai rencana awal.

---

## 7. To-Do untuk FE Agent (Flutter)

Berdasarkan rencana di `Implementation_notifaction.md` bagian "Sisi Frontend", FE perlu menyesuaikan:

### 7.1 Update Retrofit URL (WAJIB)
File `notification_api.dart` (atau setara):
```dart
// Sebelumnya (sesuai rencana awal):
@GET('/api/notifications')
@PUT('/api/notifications/{id}/read')

// Ganti menjadi:
@GET('/api/v1/notifications')
@PUT('/api/v1/notifications/{id}/read')
```

### 7.2 Tambahkan `wo_ready_for_review` ke union type
File `notification_model.dart` — perbarui komentar union type pada `NotificationData.type`:
```dart
final String type; // "wo_created" | "wo_assigned" | "wo_ready_for_review" | "wo_completed"
```

Dan tambahkan handling di UI (mis. ikon/warna berbeda untuk tipe ini, atau perlakukan sama dengan `wo_completed` sesuai keputusan desain).

### 7.3 Parsing Response
- `id` adalah **String UUID**, bukan int — pastikan `NotificationModel.id` bertipe `String`.
- Path `markAsRead` membutuhkan UUID string itu sendiri sebagai parameter.

### 7.4 Otorisasi
Endpoint memakai Sanctum Bearer token yang sama dengan endpoint lain — FE cukup memastikan interceptor Dio sudah menyertakan `Authorization: Bearer <token>` (seharusnya sudah, karena endpoint lain di project juga pakai pola yang sama).

### 7.5 Navigasi saat Item di-Tap
Saat user tap notifikasi:
1. Panggil endpoint `markAsRead` dengan `notification.id`.
2. Gunakan `notification.data.workOrderId` untuk navigasi ke halaman detail WO.
3. `data.type` bisa dipakai untuk menentukan tab/section detail yang dibuka (mis. `wo_ready_for_review` → buka tab review SPV).

### 7.6 Refresh Strategy
BE tidak push (belum ada WebSocket / FCM). FE harus poll endpoint `/notifications` sesuai strategi yang sudah direncanakan (pull-to-refresh, atau interval polling saat halaman notifikasi terbuka).

---

## 8. Quick Reference — Mapping Tipe ke User Journey

```
Superadmin buat WO
  └→ wo_created → SPV
        └→ SPV isi form & assign Staff
              └→ wo_assigned → tiap Staff
                    └→ PIC (Senior Staff) submit SELESAI
                          └→ wo_ready_for_review → SPV
                                └→ SPV approve
                                      └→ wo_completed → Superadmin
```

Dengan empat tipe ini, setiap aktor (Superadmin, SPV, Staff) selalu mendapat notifikasi pada momen yang relevan dengan peran mereka.
