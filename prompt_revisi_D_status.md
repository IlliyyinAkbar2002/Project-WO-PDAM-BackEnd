# PERINTAH REVISI (D) — Hilangkan label/badge status yang salah akibat adaptor numerik

> Relay isi di bawah ini apa adanya ke agent FE (Gemini). Ini revisi terhadap rencana sebelumnya (`planning_Gemini.md`), bagian pemetaan status WO. Implementasikan perubahannya.

## Konteks masalah

Rencana adaptormu memetakan ENUM status WO backend ke ID numerik lama:
`Pending→12 (ditugaskanKeSpv)`, `Proses→13 (ditugaskanKeStaff)`, `Selesai→6`, `Tutup→17 (ditolakManager)`.

Ini **aman untuk percabangan logika**, tetapi **TIDAK aman untuk tampilan teks**, karena:
1. **Reverse-map ambigu** — satu ENUM bisa memetakan ke beberapa ID lama (mis. `Proses ↔ 13/7/5`), sehingga round-trip ID→string→ID tidak stabil.
2. **Label/badge salah** — widget yang merender `statusId` sebagai teks akan menampilkan label legacy yang menyesatkan: WO ber-ENUM `Pending` akan tampil **"Ditugaskan ke SPV"**, `Tutup` tampil **"Ditolak Manager"**, dst. Padahal makna ENUM target berbeda.

> Catatan: inkonsistensi enum di BE sudah diperbaiki — `WorkorderController` kini konsisten memakai `Pending,Proses,Selesai,Tutup` (bukan `Ditolak`). Jadi sumber kebenaran status = **4 ENUM string** itu.

## Yang harus dilakukan

**Prinsip:** ID numerik `WorkOrderStatusId` boleh tetap dipakai untuk **percabangan logika internal**, tapi **tampilan (teks label + warna badge) HARUS diturunkan dari ENUM string asli BE**, bukan dari ID numerik.

1. **Simpan ENUM string asli di model & entity.** Pada `work_order_model.dart` / `work_order_entity.dart`, tambahkan field baru mis. `statusEnum` (String, nilai mentah `Pending/Proses/Selesai/Tutup` dari `map['status']`). Tetap isi `statusId` numerik seperti rencana untuk kompatibilitas logika lama. Jangan buang `statusId`.

2. **Buat satu sumber tunggal untuk label + warna status**, berbasis ENUM string (bukan numerik). Contoh pemetaan tampilan (Bahasa Indonesia):
   - `Pending`  → label "Menunggu", warna netral/abu.
   - `Proses`   → label "Dikerjakan", warna biru/kuning.
   - `Selesai`  → label "Selesai", warna hijau.
   - `Tutup`    → label "Ditutup", warna merah/abu gelap.
   - (defensif) nilai tak dikenal / legacy `Ditolak` → fallback aman, jangan crash.
   Letakkan helper ini di tempat yang wajar (mis. extension pada entity atau util di `core/`), dan **gunakan `statusEnum`** sebagai input — bukan `statusId`.

3. **Audit & migrasikan semua titik UI yang merender status sebagai teks/badge/warna** agar memakai helper baru di atas. Telusuri minimal: widget status chip/badge, kartu list WO (`work_order_list`, `wo_masuk`, `wo_keluar`, landing staff/spv), filter status, dan halaman detail WO. **Sertakan daftar file + baris yang kamu ubah** di hasil akhir.

4. **Filter status pada list (`fetchWorkOrders`)**: backend memfilter via `?status=<ENUM string>` (`where('status', $status)`). Kirim **ENUM string langsung**; jangan lewat ID numerik agar tidak ambigu. Jika UI filter masih berbasis ID numerik, petakan ID→ENUM string **hanya di titik kirim query**.

5. **Jangan ubah** percabangan logika yang sudah benar memakai `statusId` (mis. tombol/role gating) selama hasil fungsionalnya tetar benar; hanya pindahkan **tampilan teks/warna** ke basis ENUM string.

## Verifikasi

- WO dengan status BE `Pending` menampilkan "Menunggu" (bukan "Ditugaskan ke SPV"); `Tutup` → "Ditutup" (bukan "Ditolak Manager").
- Filter status di list memanggil `?status=Proses` (string), hasil sesuai.
- Tidak ada regresi pada tombol/aksi yang bergantung `statusId`.
- Sertakan ringkasan: daftar file diubah + sebelum/sesudah singkat untuk helper label.

## Batasan
- UI & pesan Bahasa Indonesia. Patuhi Clean Architecture repo. Setelah ubah model: `dart run build_runner build --delete-conflicting-outputs` lalu `flutter analyze`.
