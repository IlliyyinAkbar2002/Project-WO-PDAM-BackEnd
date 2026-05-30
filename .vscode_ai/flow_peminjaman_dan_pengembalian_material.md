# Alur Peminjaman dan Pengembalian Material (Work Order)

Dokumen ini menjelaskan spesifikasi kebutuhan bisnis (Business Requirements) dan alur logika teknis untuk fitur peminjaman serta pengembalian material yang terikat pada Work Order (WO). Dokumen ini ditujukan sebagai panduan implementasi bagi Backend (BE) Agent.

---

## 1. Konteks Peran & Ketentuan Umum

* **Peran Staff (Assignee)**: Pegawai lapangan (`STAFF` & `SENIOR_STAFF`) yang bertugas mengeksekusi Work Order. Mereka memiliki wewenang untuk mengajukan peminjaman material dan melaporkan pengembalian/penggunaan material.
* **Peran Supervisor (SPV) / Manajer**: Pengawas yang bertugas menyetujui (`Approve`) atau menolak (`Reject`) laporan pengembalian material dari Staff.
* **Sifat Peminjaman**: Bersifat **opsional** (tidak wajib). Peminjaman hanya dilakukan jika jenis pekerjaan pada Work Order tersebut membutuhkan material tambahan (misalnya pipa, water meter, valve).
* **Keterikatan Data**: Setiap transaksi peminjaman harus terikat secara relasional dengan `work_order_id`.

---

## 2. Alur Peminjaman Material (Borrowing Flow)

Peminjaman dilakukan oleh Staff sebelum memulai pengerjaan Work Order (`!hasMulai`) atau selama pengerjaan berlangsung (`hasMulai && !hasSelesai`).

```mermaid
sequenceDiagram
    actor Staff as Staff (Assignee)
    participant BE as Backend API
    database DB as Database

    Staff->>BE: POST /v1/workorder/{id}/peminjaman-material<br/>Payload: { material_id, jumlah_pinjam }
    activate BE
    BE->>DB: Validasi stok & kurangi stok master secara real-time
    BE->>DB: Buat record peminjaman (status: "DIPINJAM")
    BE-->>Staff: 200 OK (Auto-Approved)
    deactivate BE
```

### Aturan Bisnis Peminjaman (BE):
1. **Auto-Approval**: Pengajuan peminjaman oleh Staff **tidak memerlukan approval** dari Supervisor/Manajer.
2. **Real-time Stock Reduction**: Begitu pengajuan dikirim, Backend harus memvalidasi ketersediaan stok di tabel master material (`m_material`). Jika stok mencukupi, sistem langsung **mengurangi stok master** secara real-time dan menyimpan data transaksi peminjaman dengan status `"DIPINJAM"`.
3. **Validasi**: Batasi agar Staff tidak bisa meminjam melebihi jumlah stok yang tersedia saat itu.

---

## 3. Alur Pengembalian Material (Return & Consumption Flow)

Pengembalian dilakukan oleh Staff untuk melaporkan sisa material yang tidak terpakai, serta melaporkan material yang habis terpakai (dikonsumsi) untuk instalasi/perbaikan.

```mermaid
sequenceDiagram
    actor Staff as Staff (Assignee)
    actor SPV as Supervisor / Manager
    participant BE as Backend API
    database DB as Database

    Staff->>BE: POST /v1/peminjaman-material/{id}/kembalikan<br/>Payload: { jumlah_kembali, kondisi_kembali }
    activate BE
    BE->>DB: Simpan usulan kembali (status: "PENDING_KEMBALI")
    BE-->>Staff: 200 OK
    deactivate BE

    Note over SPV, BE: Proses Verifikasi & Approval
    SPV->>BE: POST /v1/peminjaman-material/{id}/verify<br/>Payload: { status: "APPROVED" | "REJECTED" }
    activate BE
    alt Status == APPROVED
        BE->>DB: Update status -> "DIKEMBALIKAN"
        BE->>DB: Tambahkan kembali sisa material fisik ke stok master
    else Status == REJECTED
        BE->>DB: Kembalikan status -> "DIPINJAM"
        BE->>DB: Stok master tetap berkurang
    end
    BE-->>SPV: 200 OK
    deactivate BE
```

### Aturan Bisnis Pengembalian (BE):
1. **Persetujuan Manual (Manual Approval)**: Setiap pengembalian/laporan penggunaan material **wajib melalui approval** dari Supervisor atau Manajer.
2. **Kondisi Penggunaan (Logika Instalasi)**:
   * Ketika melakukan pekerjaan (misal instalasi pipa), beberapa material dipasang di lapangan sehingga **tidak dikembalikan secara fisik** (habis terpakai).
   * Staff akan melaporkan pengembalian dengan menyertakan catatan `kondisi_kembali` (misal: *"Digunakan untuk instalasi sambungan baru"* atau *"Pipa sepanjang 2 meter habis terpakai"*).
   * Supervisor perlu meninjau laporan ini untuk memastikan material tersebut benar-benar habis digunakan untuk pekerjaan dan tidak disalahgunakan.
3. **Efek Approval / Rejection terhadap Stok**:
   * **Jika Disetujui (Approved)**:
     * Status peminjaman berubah menjadi `"DIKEMBALIKAN"`.
     * Jika ada sisa material fisik yang dikembalikan (misal: pinjam 5, pakai 3, kembali 2), maka **stok master material bertambah kembali** sejumlah sisa yang dikembalikan tersebut (dalam contoh ini, bertambah 2). Material yang dilaporkan habis terpakai (3) dianggap dikonsumsi secara permanen.
   * **Jika Ditolak (Rejected)**:
     * Status peminjaman dikembalikan ke `"DIPINJAM"`.
     * Tidak ada perubahan stok master (stok tetap berkurang sejak awal dipinjam). Staff harus melakukan pengajuan kembali dengan data/catatan yang benar.

---

## 4. Rekomendasi Endpoint & Payload API (Untuk Backend)

### A. Endpoint Peminjaman Material
* **Route**: `POST /v1/workorder/{work_order_id}/peminjaman-material`
* **Request Payload**:
  ```json
  {
    "material_id": 12,
    "jumlah_pinjam": 5
  }
  ```
* **Response (Auto-Approved)**:
  ```json
  {
    "success": true,
    "message": "Peminjaman material berhasil diproses dan stok dikurangi.",
    "data": {
      "id": 101,
      "workorder_id": 45,
      "material_id": 12,
      "jumlah_pinjam": 5,
      "waktu_pinjam": "2026-05-27T14:00:00Z",
      "status": "DIPINJAM"
    }
  }
  ```

### B. Endpoint Pengajuan Pengembalian Material
* **Route**: `POST /v1/peminjaman-material/{peminjaman_id}/kembalikan`
* **Request Payload**:
  ```json
  {
    "jumlah_kembali": 2,
    "kondisi_kembali": "Digunakan 3 meter untuk instalasi pipa dinas, sisa 2 meter dikembalikan dalam kondisi baik."
  }
  ```
* **Response**:
  ```json
  {
    "success": true,
    "message": "Pengajuan pengembalian material berhasil dikirim dan menunggu persetujuan supervisor.",
    "data": {
      "id": 101,
      "jumlah_kembali": 2,
      "kondisi_kembali": "Digunakan 3 meter untuk instalasi pipa dinas, sisa 2 meter dikembalikan dalam kondisi baik.",
      "status": "PENDING_KEMBALI"
    }
  }
  ```

### C. Endpoint Verifikasi Pengembalian (Supervisor/Manager)
* **Route**: `POST /v1/peminjaman-material/{peminjaman_id}/verify`
* **Request Payload**:
  ```json
  {
    "status": "APPROVED", // "APPROVED" atau "REJECTED"
    "catatan_verifikator": "Laporan sesuai dengan foto dokumentasi pengerjaan." // Opsional
  }
  ```
* **Response**:
  ```json
  {
    "success": true,
    "message": "Status pengembalian berhasil diperbarui menjadi APPROVED.",
    "data": {
      "id": 101,
      "status": "DIKEMBALIKAN"
    }
  }
  ```
