# Pertanyaan untuk Requirement Gathering ke Stakeholder PDAM

**Tanggal Meeting:** 26 Mei 2026
**Tim Capstone:** Illiyyin, Geo, Claude (Leader)
**Tujuan:** Validasi asumsi-asumsi yang dibuat tim developer terhadap proses bisnis riil di lapangan PDAM, agar aplikasi work order ini tidak salah desain.

---

## Catatan Pembuka untuk Tim

Ini adalah daftar pertanyaan yang **kritikal** — artinya, jawabannya **akan langsung mengubah cara aplikasi bekerja** (bukan sekadar info wajar). Tim sudah meng-asumsikan banyak hal saat ngoding (lihat catatan asumsi di bawah masing-masing pertanyaan); kalau salah, kita harus revisi kode/database.

Strategi bertanya:
1. Mulai dari **gambaran besar workflow lapangan** (jangan langsung detail teknis)
2. Lanjut ke **skenario "bagaimana jika..."** untuk validasi edge case
3. Tutup dengan **konfirmasi terminologi** (nama field, role, dll)

Bawa **alat tulis** untuk catat istilah lokal PDAM (terminology mereka harus jadi nama field kita).

---

## BAGIAN 1 — Gambaran Umum Workflow Lapangan (Wajib Tanya)

> Tujuan: validasi alur kerja paling dasar yang jadi pondasi seluruh aplikasi.

### 1.1 Tentang Pengaduan dan Awal Mula Workorder

1. **Saat ada pengaduan masuk (misal: pelanggan lapor air mati), siapa yang membuat work order — apakah selalu Superadmin di kantor pusat, atau ada role lain yang bisa buat?**
   - *Asumsi kami:* hanya Superadmin yang buat WO.

2. **Berapa lama rata-rata jeda waktu antara pengaduan masuk → WO dibuat → SPV menerima → tim lapangan berangkat?**
   - *Kenapa kami tanya:* Untuk validasi apakah ada urgency level (prioritas) yang harus kami implement.

3. **Apakah satu pengaduan selalu menghasilkan satu work order, atau satu pengaduan bisa pecah jadi beberapa WO (misal pengaduan kompleks)?**

### 1.2 Tentang Pengerjaan Lapangan

4. **Ketika SPV menerima WO dan harus menugaskan staff, apakah selalu dalam bentuk TIM (banyak orang) atau bisa INDIVIDUAL (satu orang)?**
   - *Asumsi kami:* selalu tim (minimal 1 orang), dengan satu PIC (koordinator). Kalau tim minimal 1 orang = individual juga oke.

5. **Apakah satu staff/petugas bisa di-assign ke beberapa WO sekaligus dalam satu hari?** (Misal pagi pasang meter di lokasi A, siang perbaiki pipa di lokasi B.)
   - *Asumsi kami:* boleh — tapi kalau kenyataannya tidak boleh, kami harus tambah validasi "1 staff = 1 WO aktif".

6. **Saat tim sampai di lokasi, apa LANGKAH PERTAMA yang mereka lakukan untuk memulai pekerjaan?** Apakah:
   - (a) klik tombol "Mulai" di HP, ambil foto + GPS, baru kerja?
   - (b) kerja dulu, baru lapor pas selesai?
   - (c) ada SOP lain?
   - *Asumsi kami:* (a) — klik Mulai dulu (tipe progress MULAI) dengan foto+GPS, baru kerja.

7. **Apakah selama pekerjaan berlangsung, staff WAJIB lapor progress berkala?** Atau cukup lapor "Mulai" dan "Selesai" saja?
   - *Asumsi kami:* boleh lapor progress kapan saja di antara (max 8x/hari, lihat poin 3.1).

### 1.3 Tentang Peran SPV dan PIC

8. **Apa beda persis SPV dan PIC dalam terminologi PDAM?**
   - *Asumsi kami:* SPV = atasan yang menugaskan dan review. PIC = koordinator lapangan dari dalam tim. Mohon koreksi kalau salah.

9. **Apakah PIC harus selalu seorang Senior Staff (jabatan tertentu), atau bisa staff biasa yang ditunjuk?**
   - *Asumsi kami (KRITIKAL):* hanya Senior Staff yang ditunjuk sebagai PIC yang bisa menekan tombol "Selesai". Kalau salah, banyak tim yang akan kebingungan.

10. **Apakah satu tim bisa punya LEBIH DARI SATU PIC?**

---

## BAGIAN 2 — Lokasi Kerja & GPS (KRITIKAL untuk validasi/anti-fraud)

> Tujuan: ini paling sensitif. Aplikasi sudah catat GPS staff saat lapor progres, **tapi belum dipakai untuk validasi**. Kami perlu tahu apakah PDAM butuh enforcement.

### 2.1 Geofencing (Pembatasan Wilayah)

11. **Apakah PDAM ingin sistem MENOLAK laporan staff kalau dia tidak berada di lokasi pekerjaan? (Misal: titik GPS-nya jauh dari lokasi WO.)**
   - Opsi A: Tolak keras (staff tidak bisa submit kalau di luar radius)
   - Opsi B: Tetap boleh submit, tapi sistem catat sebagai "mencurigakan" untuk audit
   - Opsi C: Cukup catat GPS untuk laporan saja, tanpa validasi
   - *Asumsi kami saat ini:* Opsi C (cuma catat). Kalau PDAM mau Opsi A/B, kami harus tambah logika geofencing.

12. **Kalau iya enforcement, berapa radius wajar dari titik lokasi WO?**
   - 10 meter? (ketat, tapi GPS error bisa 5-15m)
   - 50 meter? (standar urban)
   - 100 meter? (saat ini di kode kami, hardcoded)
   - Tergantung jenis lokasi (misal pelosok lebih besar)?

13. **Bagaimana cara PDAM menentukan TITIK lokasi WO?**
   - (a) Petugas yang assign manual tap di peta?
   - (b) Ambil dari data pengaduan (alamat pelanggan)?
   - (c) Pre-register di sistem (master lokasi yang sudah ada)?
   - *Asumsi kami:* (a). Saat SPV assign, dia tap koordinat di peta. Kalau ada master lokasi resmi PDAM (misal database semua titik meter pelanggan), kami harus integrasi.

### 2.2 Skenario Edge Case GPS

14. **Bagaimana penanganan jika lokasi pekerjaan TIDAK ADA SINYAL GPS?** (Misal di dalam ruang pompa bawah tanah, gedung bertingkat, dll.)
    - *Saat ini:* aplikasi WAJIB GPS untuk submit. Kalau tidak ada GPS, staff tidak bisa lapor.

15. **Apakah pernah ada kejadian staff "titip absen" (lapor padahal tidak di lokasi)? Apakah ini concern utama PDAM?**
    - *Kenapa kami tanya:* untuk menentukan prioritas geofencing. Kalau ini sering terjadi, geofencing harus enforced.

---

## BAGIAN 3 — Pelaporan Progres & Kuota

### 3.1 Kuota Harian

16. **Saat ini sistem kami batasi laporan progress maksimal 8 KALI PER HARI per WO. Apakah angka 8 ini wajar untuk operasional PDAM, atau ada referensi lain?**
    - *Konteks:* misal pekerjaan 3 hari = total max 24 laporan (3 × 8). MULAI tidak dihitung, Selesai tidak dihitung.

17. **Apakah pernah ada pekerjaan yang butuh lapor lebih dari 8x per hari?** (Misal kondisi emergency yang butuh update tiap 30 menit.)
    - Kalau ya, perlu mekanisme "emergency override" — boleh lebih dari 8x asalkan ada alasan.

18. **Definisi "per hari" itu mulai jam berapa?**
    - (a) Kalender (00:00 – 23:59)?
    - (b) Jam kerja (misal 08:00 – 17:00)?
    - (c) Shift?
    - *Asumsi kami:* kalender (00:00 – 23:59).

### 3.2 Saat Pekerjaan Selesai

19. **Yang menekan tombol "Selesai" itu HARUS PIC, atau anggota tim mana saja boleh?**
    - *Asumsi kami:* hanya PIC + Senior Staff. Kalau lebih flexible, kami sederhanakan.

20. **Setelah staff klik "Selesai", berapa lama biasanya SPV review?** (Untuk SLA design.)

21. **Apa yang SPV cek saat review?** (Untuk validasi field apa yang penting di-display di UI SPV.)
    - Foto hasil?
    - Lokasi GPS?
    - Hasil pengerjaan (deskripsi)?
    - Data form kategori (nomor meter, kondisi, dll)?

### 3.3 Revisi & Penolakan

22. **Berapa kali maksimal SPV boleh minta REVISI sebelum di-tolak final?** Apakah ada batas?
    - *Saat ini:* tidak ada batas (unlimited revisi). Kalau seharusnya max 3x lalu eskalasi ke manager, kami harus tambah.

23. **Kalau SPV TOLAK final, apakah pekerjaan itu BENAR-BENAR mati, atau bisa di-appeal ke manager untuk re-open?**
    - *Saat ini:* final, tidak ada appeal. Kalau ada appeal, kami harus tambah flow baru.

---

## BAGIAN 4 — Struktur Tim & Penanganan Anggota

### 4.1 Skenario Tim Bermasalah

24. **Bagaimana SOP PDAM jika di tengah pengerjaan ada anggota tim SAKIT/MENDADAK TIDAK BISA HADIR?**
    - Apakah pekerjaan lanjut dengan tim yang berkurang?
    - Apakah SPV bisa GANTI anggota tim mid-project?
    - Apakah pekerjaan harus pause?
    - *Saat ini:* tim tidak bisa diganti setelah di-assign. Ini KRITIKAL untuk dikoreksi.

25. **Bagaimana kalau PIC-nya yang sakit/cuti?** Siapa yang ambil alih dan klik "Selesai"?
    - *Saat ini:* sistem akan stuck — tidak ada cara ganti PIC.

26. **Apakah perlu pencatatan KEHADIRAN per anggota tim saat WO?** (Misal: 3 orang assigned tapi hanya 2 yang benar-benar datang.)
    - *Kenapa kami tanya:* sekarang sistem hanya catat "siapa di-assign", bukan "siapa benar-benar hadir".

### 4.2 Skenario WO Dibatalkan

27. **Apakah WO bisa DIBATALKAN setelah di-assign atau setelah pengerjaan dimulai?** Siapa yang boleh batalkan?
    - *Saat ini:* tidak ada fitur batalkan WO. Kami harus tambah endpoint cancel.

28. **Apakah WO bisa di FREEZE/PAUSE sementara?** (Misal: nunggu sparepart, nunggu izin warga.)
    - *Saat ini:* sudah ada fitur freeze/resume di kode, tapi flow-nya belum jelas. Apa skenario riil PDAM?

### 4.3 Lembur (SPL — Surat Permintaan Lembur)

29. **Bagaimana proses pengajuan lembur saat ini di PDAM?**
    - Siapa yang ajukan (staff sendiri / SPV)?
    - Siapa yang setujui (manager / superadmin)?
    - Apakah lembur SELALU terkait dengan WO, atau bisa lembur "umum"?
    - *Asumsi kami:* SPV ajukan, manager approve, dan tiap lembur disetujui otomatis bikin WO baru.

30. **Apa data wajib yang perlu diisi saat ajukan lembur?** (jenis pekerjaan, jam, alasan, dll — untuk validasi field di form.)

---

## BAGIAN 5 — Master Data & Identitas

### 5.1 Nomor Meter & Aset

31. **Apakah PDAM sudah punya database semua NOMOR METER pelanggan?**
    - Kalau ya, kami harus integrasi (jangan buat duplikat).
    - Kalau tidak, sistem kami yang akan jadi master meter.

32. **Apakah NOMOR METER itu UNIK secara global (tidak ada dua meter dengan nomor sama di seluruh PDAM)?**
    - Atau unik per WILAYAH/ZONA (misal nomor "001" boleh ada di zona A dan zona B)?
    - *Saat ini:* kami enforce unik global. Kalau salah, harus ubah database.

33. **Bagaimana format nomor meter? Ada pola tertentu?** (Misal: AB-12345, atau plain angka, dll.)
    - *Kenapa kami tanya:* untuk validasi input (regex).

34. **Apakah PDAM punya master data ASET untuk infrastruktur (pompa, reservoir, dll)?** Kalau ada, perlu integrasi.

### 5.2 Jenis Pekerjaan (JenisWorkorder)

35. **Apa saja JENIS PEKERJAAN yang ada di PDAM saat ini?** (Untuk seed data.)
    - Kategori kami sekarang ada 3: METER, JARINGAN, INFRASTRUKTUR. Apakah cukup?
    - Apakah ada jenis yang belum kami akomodasi?

36. **Untuk masing-masing jenis pekerjaan, apa FIELD WAJIB yang harus diisi?**
    - Meter: kami asumsi nomor_meter wajib.
    - Jaringan: kami asumsi jenis_pipa wajib.
    - Infrastruktur: kami asumsi nama_aset + jenis_aset wajib.

### 5.3 Struktur Organisasi

37. **Apa nama-nama JABATAN resmi di PDAM yang relevan untuk aplikasi ini?**
    - Superadmin, Manager, SPV, Senior Staff, Staff — apakah istilah ini sesuai?
    - Apakah ada level lain (Asisten Manager, Kepala Bagian, dll)?

38. **Apa nama DEPARTEMEN/UNIT yang ada di PDAM?** (Untuk seed master data.)

---

## BAGIAN 6 — Foto, Dokumentasi & Bukti

39. **Berapa MINIMAL dan MAKSIMAL foto yang harus dilampirkan saat:**
    - Klik "Mulai"?
    - Lapor progress?
    - Klik "Selesai"?
    - *Saat ini:* tidak ada minimal (boleh tanpa foto). Kalau seharusnya wajib min 1 foto, kami tambah validasi.

40. **Apa yang HARUS ada di foto?** (Misal kondisi sebelum/sesudah, papan info, anggota tim, dll.)

41. **Apakah foto harus ada WATERMARK timestamp + GPS?** (Untuk anti-fraud.)
    - *Saat ini:* tidak ada watermark.

42. **Berapa LAMA dokumentasi harus disimpan?** (Untuk planning storage.)

---

## BAGIAN 7 — Notifikasi & Komunikasi

43. **Bagaimana cara SPV TAHU bahwa ada WO baru yang ditugaskan ke dia?**
    - Push notification di HP?
    - Email?
    - SMS?
    - Cek manual di aplikasi?

44. **Bagaimana cara staff TAHU bahwa dia di-assign ke tim WO?**

45. **Apakah perlu chat/komentar antara SPV dan staff di dalam aplikasi?**

---

## BAGIAN 8 — Pelaporan & Output

46. **Apa LAPORAN yang harus bisa di-generate dari sistem?** (Untuk planning fitur export.)
    - Laporan WO per periode?
    - Laporan kinerja per staff?
    - Laporan per jenis pekerjaan?
    - KPI report?

47. **Format ekspor yang dibutuhkan?** (PDF, Excel, dll.)

48. **Siapa konsumennya?** (Manager, direksi, regulator?)

---

## BAGIAN 9 — Konfirmasi Terminologi (Tutup Meeting)

Setelah dapat jawaban di atas, pastikan istilah-istilah ini benar (atau koreksi):

| Istilah Kode Kami | Istilah PDAM? |
|---|---|
| Workorder | ? |
| SPV | ? |
| PIC | ? |
| Senior Staff | ? |
| Progress | ? |
| Pengecekan | ? |
| Revisi | ? |
| Lembur SPL | ? |
| Geofencing | ? |
| Master Lokasi | ? |

---

## Lampiran — Daftar Asumsi Developer yang Akan Tervalidasi

Setelah meeting, kita refresh dokumen ini dengan jawaban PDAM, lalu update kode di tempat yang relevan. Asumsi-asumsi utama yang menunggu validasi:

- [ ] Kuota 8x laporan/hari (lihat `ProgressWorkorderController.php`)
- [ ] Radius geofencing 100m hardcoded (lihat `AssignmentService.php`)
- [ ] Geofencing TIDAK di-enforce (cuma catat)
- [ ] Hanya PIC+Senior Staff yang boleh submit SELESAI
- [ ] Satu WO = satu Assignment (tidak bisa split)
- [ ] Tim tidak bisa diganti setelah assign
- [ ] PIC tidak bisa diganti setelah assign
- [ ] WO tidak bisa dibatalkan setelah assign
- [ ] Nomor meter UNIK secara global
- [ ] Revisi unlimited (tidak ada batas)
- [ ] Penolakan final (tidak bisa di-appeal)
- [ ] Initial progress (MULAI/SELESAI) belum auto-created saat WO dibuat
- [ ] Foto tidak wajib, tidak ada watermark
- [ ] Belum ada sistem notifikasi

---

**Tips terakhir:** Kalau stakeholder PDAM kelihatan ragu jawab pertanyaan teknis, **arahkan ke skenario lapangan konkret** ("Misalnya kemarin Pak Budi lagi pasang meter, terus...") — jawaban dari skenario nyata jauh lebih akurat daripada jawaban abstrak.
