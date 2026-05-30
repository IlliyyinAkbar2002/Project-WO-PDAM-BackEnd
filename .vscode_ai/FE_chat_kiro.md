Oke, saya analisis TodoList-nya. Mari saya cek kode dulu untuk melihat:
1. Bagaimana FE membaca data petugas dari response BE
2. Bagaimana tombol "Mulai" mengirim progress

Now let me look at the `work_order_progress_remote_data_source.dart` to understand how the start progress endpoint works, and also look at the assignee detail page to see how petugas is displayed:

Now let me check how petugas is displayed in the custom field widget (the chip-style "assignee" field). The image 3 shows petugas as chips "David 1234567894 ✕" and "Budi 1234567895 ✕":

Good. Now I have enough context. Let me analyze each todo item:

## Analisis TodoList

### 1. Backend: Setelah assign staff, apakah ada payload dikirim kembali?

**Ini di sisi Backend.** Dari kode FE, method `assignStaff()` hanya `return const DataSuccess(null)` — artinya FE tidak memanfaatkan response body dari BE. Setelah assign berhasil, FE trigger `GetWorkOrderDetailEvent` untuk fetch ulang detail WO.

**Yang perlu BE pastikan:** Setelah `POST /v1/workorder/{id}/assign-staff` sukses, response sebaiknya mengembalikan data WO terupdate lengkap dengan relasi `assignment_members` / `petugas_list` supaya FE bisa langsung refresh tanpa harus hit endpoint detail lagi. Tapi ini opsional — karena FE sudah melakukan re-fetch.

### 2. FE: Setelah di-assign oleh SPV, petugas harus tampil

Saya lihat di image 1 (WO Infrastruktur), petugas **sudah tampil** sebagai chip "David" dan "Budi" dengan Koordinator PIC "David". Jadi flow ini sudah jalan — `_resolveKategoriForm` + parsing `assignment_members` sudah bekerja.

**Tapi ada bug di image 2 (Work Order Masuk list):** WO yang sudah di-assign staff masih status "Ditugaskan ke SPV" dan badge masih "Meter". Ini dua masalah:
- **Badge "Meter" untuk semua WO** → inferensi nama tidak jalan di list (atau BE kirim kategori_form = "meter" untuk semua)
- **Status "Ditugaskan ke SPV" padahal sudah assigned** → BE belum update status WO setelah assign-staff sukses (issue di BE)

### 3. FE: Tombol "Mulai" harus kirim payload ke `progress_workorder`

**Sudah benar.** Dari kode `work_order_progress_remote_data_source.dart` line 287-307:
- Kalau `tipeProgressId == 1` (Mulai) dan `id == null`, FE POST ke `/v1/progress-workorder/start` dengan payload `workorder_id` + `tipe_progress_kode: "Mulai"` (multipart)
- BE menyimpan ke tabel `progress_workorder`

Ini flow sudah benar.

---

## Action untuk FE

Berdasarkan analisis:
- **#1**: BE issue (tidak perlu fix FE, tapi kamu bisa minta BE return data lengkap)
- **#2**: Petugas sudah tampil di detail ✅ — tapi badge/status di list page masih masalah
- **#3**: Sudah benar ✅

Mau saya fokus perbaiki hal apa? Berdasarkan image 2, masalah paling terlihat adalah **badge di list page masih "Meter" untuk WO Jaringan/Infrastruktur dan status tidak berubah setelah assign**.

Untuk masalah itu:
- **Badge "Meter"** → kemungkinan BE mengirim `kategori_form: "meter"` dari relasi `jenis_workorder` (karena semua WO pakai `jenis_workorder_id = 1` = meter). Di detail page badge benar karena ada record `wo_jaringan`/`wo_infrastruktur` setelah assign, tapi di list page mungkin endpoint list tidak include relasi tersebut. **Ini harus dicek di BE** — pastikan endpoint list WO include relasi `wo_jaringan`/`wo_infrastruktur`/`wo_meter` atau kirim `kategori_form` yang benar.
- **Status tidak berubah** → BE issue, setelah assign-staff sukses, status WO harus otomatis berubah dari `DITUGASKAN_KE_SPV` ke status berikutnya (misal `DITUGASKAN_KE_STAFF`).

Konfirmasi dulu, fokusnya mau ke mana?