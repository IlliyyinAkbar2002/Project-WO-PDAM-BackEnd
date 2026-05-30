# OpenAPI Contract — Work Order Management PDAM

> **Status:** Draft v1 (mencerminkan keputusan Q1–Q14).
>
> **Format:** OpenAPI 3.0.3 dalam YAML. Kedua FE (Next.js & Flutter) **wajib mengikuti spesifikasi ini**. Perubahan response shape → update YAML dulu → review bersama → baru implement.
>
> **Base URL dev:** `http://127.0.0.1:8000/api/v1`
>
> **Auth:** Bearer Token (Sanctum Personal Access Token).

---

## Ringkasan Endpoint

| Grup | Method | Path | Role |
|------|--------|------|------|
| Auth | POST | `/auth/login` | Public |
| Auth | POST | `/auth/logout` | Any |
| Auth | GET | `/auth/me` | Any |
| Master | GET | `/master/departemen` | Any |
| Master | GET | `/master/jenis-workorder` | Any |
| Master | GET | `/master/jenis-workorder/{id}/form-template` | Any |
| Master | GET | `/master/jenis-pengaduan` | Any |
| Master | GET | `/master/status` | Any |
| Master | GET | `/master/tipe-progress` | Any |
| Pengaduan | GET | `/pengaduan` | Super Admin, Manager |
| Pengaduan | GET | `/pengaduan/{id}` | Super Admin, Manager |
| Workorder | GET | `/workorder` | Any (filtered by role) |
| Workorder | GET | `/workorder/{id}` | Any (with policy) |
| Workorder | POST | `/workorder` | Super Admin |
| Workorder | POST | `/workorder/{id}/assign-spv` | Manager |
| Workorder | POST | `/workorder/{id}/assign-staff` | SPV (pic) |
| Workorder | POST | `/workorder/{id}/approve` | Manager |
| Workorder | POST | `/workorder/{id}/reject` | Manager |
| Progress | POST | `/progress-workorder/{id}/mulai` | Staff (anggota WO) |
| Progress | POST | `/progress-workorder/{id}/submit` | Staff (anggota WO) |
| Progress | POST | `/progress-workorder/{id}/terima` | SPV (pic WO) |
| Progress | POST | `/progress-workorder/{id}/revisi` | SPV (pic WO) |
| Progress | POST | `/progress-workorder/{id}/tolak` | SPV (pic WO) |
| Dokumentasi | POST | `/progress-workorder/{id}/dokumentasi` | Staff (anggota WO) |
| Laporan | GET | `/laporan-workorder/{id}` | Any (with policy) |
| Laporan | GET | `/laporan-workorder/{id}/pdf` | Any (with policy) |

---

## Spesifikasi OpenAPI

```yaml
openapi: 3.0.3
info:
  title: PDAM Work Order Management API
  version: 1.0.0
  description: |
    Backend API untuk aplikasi manajemen Work Order PDAM Perumda Surabaya.
    Melayani 2 frontend: Web Next.js (Admin, Manager) dan Mobile Flutter (SPV, Staff).

servers:
  - url: http://127.0.0.1:8000/api/v1
    description: Development (local)
  - url: https://wo-api.example.com/api/v1
    description: Staging

security:
  - BearerAuth: []

tags:
  - name: Auth
  - name: Master
  - name: Pengaduan
  - name: Workorder
  - name: Progress
  - name: Dokumentasi
  - name: Laporan

# ---------------------------------------------------------------------------
# PATHS
# ---------------------------------------------------------------------------
paths:
  # ==================== AUTH ====================
  /auth/login:
    post:
      tags: [Auth]
      summary: Login user
      security: []
      requestBody:
        required: true
        content:
          application/json:
            schema:
              type: object
              required: [email, password]
              properties:
                email:    { type: string, format: email, example: "satoshi@gmail.com" }
                password: { type: string, format: password, example: "password" }
      responses:
        '200':
          description: OK
          content:
            application/json:
              schema:
                type: object
                properties:
                  data:
                    type: object
                    properties:
                      token: { type: string, example: "1|lKj3...ABC" }
                      user:  { $ref: '#/components/schemas/User' }
        '422': { $ref: '#/components/responses/ValidationError' }
        '401': { $ref: '#/components/responses/Unauthenticated' }

  /auth/logout:
    post:
      tags: [Auth]
      summary: Revoke current token
      responses:
        '200':
          description: OK
          content:
            application/json:
              schema:
                type: object
                properties:
                  message: { type: string, example: "Logged out" }

  /auth/me:
    get:
      tags: [Auth]
      summary: Get current authenticated user
      responses:
        '200':
          description: OK
          content:
            application/json:
              schema:
                type: object
                properties:
                  data: { $ref: '#/components/schemas/User' }

  # ==================== MASTER DATA ====================
  /master/departemen:
    get:
      tags: [Master]
      summary: List departemen (2 row)
      responses:
        '200':
          description: OK
          content:
            application/json:
              schema:
                type: object
                properties:
                  data:
                    type: array
                    items: { $ref: '#/components/schemas/Departemen' }

  /master/jenis-workorder:
    get:
      tags: [Master]
      summary: List jenis workorder
      responses:
        '200':
          description: OK
          content:
            application/json:
              schema:
                type: object
                properties:
                  data:
                    type: array
                    items: { $ref: '#/components/schemas/JenisWorkorder' }

  /master/jenis-workorder/{id}/form-template:
    get:
      tags: [Master]
      summary: Ambil form_template untuk jenis WO (skema form)
      parameters:
        - $ref: '#/components/parameters/IdPath'
      responses:
        '200':
          description: OK
          content:
            application/json:
              schema:
                type: object
                properties:
                  data:
                    type: array
                    items: { $ref: '#/components/schemas/FormTemplateField' }
        '404': { $ref: '#/components/responses/NotFound' }

  /master/jenis-pengaduan:
    get:
      tags: [Master]
      summary: List jenis pengaduan
      responses:
        '200':
          description: OK
          content:
            application/json:
              schema:
                type: object
                properties:
                  data:
                    type: array
                    items: { $ref: '#/components/schemas/JenisPengaduan' }

  /master/status:
    get:
      tags: [Master]
      summary: List semua m_status (dengan kode)
      responses:
        '200': { description: OK }

  /master/tipe-progress:
    get:
      tags: [Master]
      summary: List m_tipe_progress
      responses:
        '200': { description: OK }

  # ==================== PENGADUAN ====================
  /pengaduan:
    get:
      tags: [Pengaduan]
      summary: List pengaduan
      parameters:
        - in: query
          name: status_kode
          schema: { type: string, enum: [DITERIMA, DITINDAK, SELESAI, DITOLAK, DUPLIKAT] }
        - in: query
          name: jenis_kode
          schema: { type: string }
        - in: query
          name: search
          schema: { type: string }
        - $ref: '#/components/parameters/Page'
        - $ref: '#/components/parameters/PerPage'
      responses:
        '200':
          description: OK
          content:
            application/json:
              schema:
                type: object
                properties:
                  data:
                    type: array
                    items: { $ref: '#/components/schemas/Pengaduan' }
                  meta: { $ref: '#/components/schemas/PaginationMeta' }

  /pengaduan/{id}:
    get:
      tags: [Pengaduan]
      summary: Detail pengaduan (termasuk WO terkait)
      parameters:
        - $ref: '#/components/parameters/IdPath'
      responses:
        '200':
          description: OK
          content:
            application/json:
              schema:
                type: object
                properties:
                  data:
                    allOf:
                      - $ref: '#/components/schemas/Pengaduan'
                      - type: object
                        properties:
                          workorders:
                            type: array
                            items: { $ref: '#/components/schemas/WorkorderSummary' }
        '404': { $ref: '#/components/responses/NotFound' }

  # ==================== WORKORDER ====================
  /workorder:
    get:
      tags: [Workorder]
      summary: List WO (otomatis di-filter berdasarkan role)
      description: |
        - Super Admin: semua WO
        - Manager: WO di departemennya
        - SPV: WO dimana dia `pic_id` + WO dimana dia di `workorder_petugas`
        - Staff: WO dimana dia di `workorder_petugas`
      parameters:
        - in: query
          name: status_kode
          schema: { type: string }
        - in: query
          name: jenis_workorder_id
          schema: { type: integer }
        - in: query
          name: tanggal_dari
          schema: { type: string, format: date }
        - in: query
          name: tanggal_sampai
          schema: { type: string, format: date }
        - in: query
          name: user_id
          schema: { type: integer }
          description: Filter petugas tertentu
        - in: query
          name: search
          schema: { type: string }
          description: Search by judul_pekerjaan
        - $ref: '#/components/parameters/Page'
        - $ref: '#/components/parameters/PerPage'
      responses:
        '200':
          description: OK
          content:
            application/json:
              schema:
                type: object
                properties:
                  data:
                    type: array
                    items: { $ref: '#/components/schemas/WorkorderSummary' }
                  meta: { $ref: '#/components/schemas/PaginationMeta' }

    post:
      tags: [Workorder]
      summary: "[Super Admin] Buat WO baru"
      requestBody:
        required: true
        content:
          application/json:
            schema: { $ref: '#/components/schemas/WorkorderCreateRequest' }
      responses:
        '201':
          description: Created
          content:
            application/json:
              schema:
                type: object
                properties:
                  message: { type: string, example: "Workorder created" }
                  data:    { $ref: '#/components/schemas/Workorder' }
        '422': { $ref: '#/components/responses/ValidationError' }

  /workorder/{id}:
    get:
      tags: [Workorder]
      summary: Detail WO (penuh, termasuk progress, petugas, form_values)
      parameters:
        - $ref: '#/components/parameters/IdPath'
      responses:
        '200':
          description: OK
          content:
            application/json:
              schema:
                type: object
                properties:
                  data: { $ref: '#/components/schemas/Workorder' }
        '403': { $ref: '#/components/responses/Forbidden' }
        '404': { $ref: '#/components/responses/NotFound' }

  /workorder/{id}/assign-spv:
    post:
      tags: [Workorder]
      summary: "[Manager] Assign SPV ke WO (PIC)"
      description: |
        Policy: Manager hanya bisa assign di WO yang `departemen_id` == departemen Manager.
      parameters:
        - $ref: '#/components/parameters/IdPath'
      requestBody:
        required: true
        content:
          application/json:
            schema:
              type: object
              required: [pic_id]
              properties:
                pic_id:    { type: integer, description: "user_id SPV" }
                catatan:   { type: string, nullable: true }
      responses:
        '200':
          description: OK
          content:
            application/json:
              schema:
                type: object
                properties:
                  message: { type: string, example: "SPV berhasil ditugaskan" }
                  data:    { $ref: '#/components/schemas/Workorder' }
        '403': { $ref: '#/components/responses/Forbidden' }
        '422': { $ref: '#/components/responses/ValidationError' }

  /workorder/{id}/assign-staff:
    post:
      tags: [Workorder]
      summary: "[SPV] Assign multi-Staff ke WO"
      description: |
        Policy: hanya SPV yang `pic_id == auth.user.id` untuk WO ini.
        `petugas_id` bisa array — akan di-sync ke pivot `workorder_petugas`.
      parameters:
        - $ref: '#/components/parameters/IdPath'
      requestBody:
        required: true
        content:
          application/json:
            schema:
              type: object
              required: [petugas_id]
              properties:
                petugas_id:
                  type: array
                  items: { type: integer }
                  minItems: 1
                  example: [5, 6, 7]
      responses:
        '200':
          description: OK
          content:
            application/json:
              schema:
                type: object
                properties:
                  message: { type: string, example: "Staff berhasil ditugaskan" }
                  data:    { $ref: '#/components/schemas/Workorder' }

  /workorder/{id}/approve:
    post:
      tags: [Workorder]
      summary: "[Manager] Approve WO final → trigger auto-generate laporan"
      parameters:
        - $ref: '#/components/parameters/IdPath'
      requestBody:
        content:
          application/json:
            schema:
              type: object
              properties:
                catatan: { type: string, nullable: true }
      responses:
        '200':
          description: OK
          content:
            application/json:
              schema:
                type: object
                properties:
                  message: { type: string, example: "Approved, laporan diterbitkan" }
                  data:
                    type: object
                    properties:
                      workorder: { $ref: '#/components/schemas/Workorder' }
                      laporan:   { $ref: '#/components/schemas/LaporanWorkorder' }

  /workorder/{id}/reject:
    post:
      tags: [Workorder]
      summary: "[Manager] Reject WO → status DITOLAK_MANAGER, balik ke staff"
      parameters:
        - $ref: '#/components/parameters/IdPath'
      requestBody:
        required: true
        content:
          application/json:
            schema:
              type: object
              required: [alasan]
              properties:
                alasan: { type: string, minLength: 5 }
      responses:
        '200':
          description: OK

  # ==================== PROGRESS WORKORDER ====================
  /progress-workorder/{id}/mulai:
    post:
      tags: [Progress]
      summary: "[Staff] Klik 'Mulai' pada WO"
      description: Ubah progress `MULAI` → status `SUBMITTED`, update WO → `DALAM_PENGERJAAN`.
      parameters:
        - $ref: '#/components/parameters/IdPath'
      responses:
        '200':
          description: OK
          content:
            application/json:
              schema:
                type: object
                properties:
                  data: { $ref: '#/components/schemas/ProgressWorkorder' }

  /progress-workorder/{id}/submit:
    post:
      tags: [Progress]
      summary: "[Staff] Submit hasil WO (form_values + narasi)"
      description: |
        - Progress tipe `SELESAI`: isi `hasil_pengerjaan` + `form_values` (tervalidasi by `form_template`).
        - Progress tipe `REVISI` (dari SPV): isi hanya field yang diminta di `field_to_revise`.
      parameters:
        - $ref: '#/components/parameters/IdPath'
      requestBody:
        required: true
        content:
          application/json:
            schema:
              type: object
              required: [hasil_pengerjaan]
              properties:
                hasil_pengerjaan:
                  type: string
                  example: "Pipa utama di RW 05 sudah diganti. Tekanan air kembali normal."
                form_values:
                  type: object
                  description: "Key-value sesuai form_template jenis WO. Validasi backend."
                  example:
                    diameter_pipa: 5
                    bahan_pipa: "PVC"
                    tanggal_selesai_aktual: "2026-01-23"
      responses:
        '200':
          description: OK
          content:
            application/json:
              schema:
                type: object
                properties:
                  message: { type: string, example: "Hasil berhasil disubmit. Menunggu verifikasi SPV." }
                  data: { $ref: '#/components/schemas/ProgressWorkorder' }
        '422': { $ref: '#/components/responses/ValidationError' }

  /progress-workorder/{id}/terima:
    post:
      tags: [Progress]
      summary: "[SPV] Terima hasil Staff → lanjut ke Manager"
      parameters:
        - $ref: '#/components/parameters/IdPath'
      requestBody:
        content:
          application/json:
            schema:
              type: object
              properties:
                catatan: { type: string, nullable: true }
      responses:
        '200':
          description: OK

  /progress-workorder/{id}/revisi:
    post:
      tags: [Progress]
      summary: "[SPV] Minta Revisi — Staff lengkapi field tertentu"
      description: |
        **Append pattern (Q12):** row SELESAI lama ditandai `REVISI_REQUESTED`, row baru tipe `REVISI`
        di-append berisi catatan SPV. Staff hanya perlu isi ulang field di `field_to_revise`.
      parameters:
        - $ref: '#/components/parameters/IdPath'
      requestBody:
        required: true
        content:
          application/json:
            schema:
              type: object
              required: [alasan, field_to_revise]
              properties:
                alasan:
                  type: string
                  minLength: 5
                  example: "Foto dokumentasi kurang jelas, tolong upload ulang dengan pencahayaan cukup."
                field_to_revise:
                  type: array
                  items: { type: string }
                  minItems: 1
                  example: ["foto_sesudah", "keterangan_material"]
                lampiran_urls:
                  type: array
                  items: { type: string, format: uri }
                  description: Opsional — URL lampiran revisi dari SPV
      responses:
        '200':
          description: OK

  /progress-workorder/{id}/tolak:
    post:
      tags: [Progress]
      summary: "[SPV] Tolak hasil Staff (final) → WO DITOLAK_SPV"
      description: |
        **Append pattern (Q12):** row SELESAI lama ditandai `DITOLAK_SPV`, row baru tipe `DITOLAK` di-append.
        WO berstatus final `DITOLAK_SPV` — tidak lanjut ke Manager.
      parameters:
        - $ref: '#/components/parameters/IdPath'
      requestBody:
        required: true
        content:
          application/json:
            schema:
              type: object
              required: [alasan]
              properties:
                alasan:
                  type: string
                  minLength: 5
                  example: "Lokasi pekerjaan tidak sesuai instruksi WO."
      responses:
        '200':
          description: OK

  # ==================== DOKUMENTASI ====================
  /progress-workorder/{id}/dokumentasi:
    post:
      tags: [Dokumentasi]
      summary: "[Staff] Upload foto dokumentasi ke progres"
      parameters:
        - $ref: '#/components/parameters/IdPath'
      requestBody:
        required: true
        content:
          multipart/form-data:
            schema:
              type: object
              required: [file]
              properties:
                file:
                  type: string
                  format: binary
                  description: "Max 2MB, tipe: image/jpeg | image/png | image/webp"
                jenis:
                  type: string
                  enum: [HASIL_KERJA, LAMPIRAN_REVISI]
                  default: HASIL_KERJA
      responses:
        '201':
          description: Created
          content:
            application/json:
              schema:
                type: object
                properties:
                  data: { $ref: '#/components/schemas/DokumentasiProgress' }

  # ==================== LAPORAN ====================
  /laporan-workorder/{id}:
    get:
      tags: [Laporan]
      summary: Detail laporan WO
      parameters:
        - $ref: '#/components/parameters/IdPath'
      responses:
        '200':
          description: OK
          content:
            application/json:
              schema:
                type: object
                properties:
                  data: { $ref: '#/components/schemas/LaporanWorkorder' }
        '404': { $ref: '#/components/responses/NotFound' }

  /laporan-workorder/{id}/pdf:
    get:
      tags: [Laporan]
      summary: Download PDF laporan
      parameters:
        - $ref: '#/components/parameters/IdPath'
      responses:
        '200':
          description: PDF binary
          content:
            application/pdf:
              schema:
                type: string
                format: binary
        '302':
          description: Redirect ke Cloudinary / URL publik (kalau pdf_url sudah di-set)

# ---------------------------------------------------------------------------
# COMPONENTS
# ---------------------------------------------------------------------------
components:

  securitySchemes:
    BearerAuth:
      type: http
      scheme: bearer
      bearerFormat: Sanctum

  parameters:
    IdPath:
      in: path
      name: id
      required: true
      schema: { type: integer, example: 1 }
    Page:
      in: query
      name: page
      schema: { type: integer, default: 1 }
    PerPage:
      in: query
      name: per_page
      schema: { type: integer, default: 15, maximum: 100 }

  responses:
    ValidationError:
      description: Validation error (422)
      content:
        application/json:
          schema:
            type: object
            properties:
              message: { type: string, example: "The given data was invalid." }
              errors:
                type: object
                additionalProperties:
                  type: array
                  items: { type: string }
                example:
                  judul_pekerjaan: ["The judul pekerjaan field is required."]
                  pic_id: ["The selected pic id is invalid."]

    Unauthenticated:
      description: Auth token missing/invalid (401)
      content:
        application/json:
          schema:
            type: object
            properties:
              message: { type: string, example: "Unauthenticated." }

    Forbidden:
      description: Policy denied (403)
      content:
        application/json:
          schema:
            type: object
            properties:
              message: { type: string, example: "This action is unauthorized." }

    NotFound:
      description: Resource not found (404)
      content:
        application/json:
          schema:
            type: object
            properties:
              message: { type: string, example: "Resource not found." }

  schemas:
    # ---------- Core ----------
    User:
      type: object
      properties:
        id: { type: integer }
        email: { type: string, format: email }
        role: { $ref: '#/components/schemas/Role' }
        pegawai:
          type: object
          properties:
            id: { type: integer }
            nama: { type: string }
            nip: { type: string }
            departemen: { $ref: '#/components/schemas/Departemen' }
            jabatan: { $ref: '#/components/schemas/Jabatan' }

    Role:
      type: object
      properties:
        id: { type: integer }
        nama: { type: string, example: "Employee" }

    Jabatan:
      type: object
      properties:
        id: { type: integer }
        nama: { type: string }

    Departemen:
      type: object
      properties:
        id: { type: integer }
        nama: { type: string, enum: [Operasional, Pelayanan] }

    JenisWorkorder:
      type: object
      properties:
        id: { type: integer }
        nama: { type: string }
        deskripsi: { type: string, nullable: true }

    Status:
      type: object
      properties:
        id: { type: integer }
        kode: { type: string, example: "DALAM_PENGERJAAN" }
        nama: { type: string }

    TipeProgress:
      type: object
      properties:
        id: { type: integer }
        kode: { type: string, enum: [MULAI, PROGRESS, SELESAI, REVISI, DITOLAK] }
        nama: { type: string }

    FormTemplateField:
      type: object
      properties:
        id: { type: integer }
        jenis_workorder_id: { type: integer }
        nama_field: { type: string, example: "diameter_pipa" }
        label: { type: string, example: "Diameter Pipa" }
        tipe_field: { type: string, enum: [text, number, date, boolean, select, textarea] }
        opsi:
          type: array
          items: { type: string }
          nullable: true
        required: { type: boolean }
        validasi:
          type: object
          additionalProperties: true
          nullable: true
          description: "Object validasi, mis. {min:1, max:100}"
        urutan: { type: integer }

    JenisPengaduan:
      type: object
      properties:
        id: { type: integer }
        kode: { type: string, example: "KEBOCORAN" }
        nama: { type: string, example: "Kebocoran" }

    Pengaduan:
      type: object
      properties:
        id: { type: integer }
        external_id: { type: string, nullable: true }
        nomor_pelanggan: { type: string, nullable: true }
        nama_pelapor: { type: string }
        kontak_pelapor: { type: string, nullable: true }
        alamat: { type: string, nullable: true }
        jenis_pengaduan: { $ref: '#/components/schemas/JenisPengaduan' }
        status: { $ref: '#/components/schemas/Status' }
        departemen:
          allOf: [{ $ref: '#/components/schemas/Departemen' }]
          nullable: true
        duplicate_of_id: { type: integer, nullable: true }
        deskripsi: { type: string }
        tanggal_lapor: { type: string, format: date-time }

    WorkorderCreateRequest:
      type: object
      required:
        - judul_pekerjaan
        - jenis_workorder_id
        - tipe_workorder_id
        - jenis_lokasi_id
        - waktu_penugasan
        - estimasi_durasi
        - unit_waktu
        - estimasi_selesai
        - departemen_id
      properties:
        judul_pekerjaan: { type: string, maxLength: 255 }
        jenis_workorder_id: { type: integer }
        tipe_workorder_id: { type: integer }
        jenis_lokasi_id: { type: integer }
        location_id: { type: integer, nullable: true }
        latitude: { type: number, format: float, nullable: true }
        longitude: { type: number, format: float, nullable: true }
        waktu_penugasan: { type: string, format: date-time }
        estimasi_durasi: { type: integer, minimum: 1 }
        unit_waktu: { type: string, enum: [Menit, Jam, Hari] }
        estimasi_selesai: { type: string, format: date-time }
        departemen_id: { type: integer }
        pengaduan_id: { type: integer, nullable: true }

    WorkorderSummary:
      type: object
      properties:
        id: { type: integer }
        judul_pekerjaan: { type: string }
        waktu_penugasan: { type: string, format: date-time }
        estimasi_selesai: { type: string, format: date-time }
        status: { $ref: '#/components/schemas/Status' }
        jenis_workorder: { $ref: '#/components/schemas/JenisWorkorder' }
        departemen: { $ref: '#/components/schemas/Departemen' }
        pic: { $ref: '#/components/schemas/User' }
        manager:
          allOf: [{ $ref: '#/components/schemas/User' }]
          nullable: true
        petugas_list:
          type: array
          items: { $ref: '#/components/schemas/User' }

    Workorder:
      allOf:
        - $ref: '#/components/schemas/WorkorderSummary'
        - type: object
          properties:
            form_values:
              type: object
              additionalProperties: true
              nullable: true
              description: "Hasil isian form jenis WO (Q1). Schema dinamis sesuai form_template."
            pengaduan:
              allOf: [{ $ref: '#/components/schemas/Pengaduan' }]
              nullable: true
            approved_by:
              allOf: [{ $ref: '#/components/schemas/User' }]
              nullable: true
            approved_at: { type: string, format: date-time, nullable: true }
            approval_notes: { type: string, nullable: true }
            progress_workorder:
              type: array
              items: { $ref: '#/components/schemas/ProgressWorkorder' }

    ProgressWorkorder:
      type: object
      properties:
        id: { type: integer }
        workorder_id: { type: integer }
        tipe_progress: { $ref: '#/components/schemas/TipeProgress' }
        status: { $ref: '#/components/schemas/Status' }
        order: { type: integer }
        hasil_pengerjaan: { type: string, nullable: true }
        waktu_submit: { type: string, format: date-time, nullable: true }
        submitter:
          allOf: [{ $ref: '#/components/schemas/User' }]
          nullable: true
        reviewer:
          allOf: [{ $ref: '#/components/schemas/User' }]
          nullable: true
        reviewed_at: { type: string, format: date-time, nullable: true }
        alasan_penolakan: { type: string, nullable: true }
        field_to_revise:
          type: array
          items: { type: string }
          nullable: true
        dokumentasi:
          type: array
          items: { $ref: '#/components/schemas/DokumentasiProgress' }

    DokumentasiProgress:
      type: object
      properties:
        id: { type: integer }
        progress_workorder_id: { type: integer }
        url: { type: string, format: uri }
        jenis: { type: string, enum: [HASIL_KERJA, LAMPIRAN_REVISI] }
        created_at: { type: string, format: date-time }

    LaporanWorkorder:
      type: object
      properties:
        id: { type: integer }
        workorder_id: { type: integer }
        nomor_laporan: { type: string, example: "LAP-WO-2026-0001" }
        tanggal_terbit: { type: string, format: date-time }
        ringkasan_pekerjaan: { type: string, nullable: true }
        hasil_akhir_snapshot:
          type: object
          additionalProperties: true
          nullable: true
        petugas_snapshot:
          type: array
          items:
            type: object
            properties:
              user_id: { type: integer }
              nama: { type: string }
              nip: { type: string }
        catatan_spv: { type: string, nullable: true }
        catatan_manager: { type: string, nullable: true }
        pdf_url: { type: string, format: uri, nullable: true }
        issued_by: { $ref: '#/components/schemas/User' }
        approved_by: { $ref: '#/components/schemas/User' }
        approved_at: { type: string, format: date-time }

    PaginationMeta:
      type: object
      properties:
        current_page: { type: integer }
        last_page: { type: integer }
        per_page: { type: integer }
        total: { type: integer }
```

---

## Convention & Catatan untuk Tim

### 1. Format Response Seragam
- Data tunggal → `{ "data": {...}, "message": "..." }`
- Data list dengan pagination → `{ "data": [...], "meta": { pagination... } }`
- Error → `{ "message": "...", "errors": { field: [msg] } }` (422) atau `{ "message": "..." }` (401/403/404/500)

### 2. Status Code Map
| Event | Code |
|---|---|
| OK | 200 |
| Created | 201 |
| Validation error | 422 |
| Tidak ada auth token | 401 |
| Ditolak policy | 403 |
| Resource tidak ditemukan | 404 |
| Server error | 500 |

### 3. Konvensi Naming
- URL: **kebab-case** (`/progress-workorder/{id}/tolak`)
- JSON field: **snake_case** (`judul_pekerjaan`, `petugas_list`)
- Query parameter: **snake_case** (`jenis_workorder_id`)

### 4. Workflow Development
1. Jika kamu (Nika/Geo) mau **tambah / ubah endpoint**:
   - Update YAML di atas terlebih dahulu
   - Open Draft PR dengan perubahan YAML
   - Minta review singkat
   - Baru implement backend + FE

2. **Jangan langsung coding tanpa update YAML** — ini merusak kontrak.

### 5. Tool Rekomendasi
- **Preview**: paste YAML ke [editor.swagger.io](https://editor.swagger.io/)
- **Mock Server**: Prism CLI (`npm i -g @stoplight/prism-cli` → `prism mock openapi.yaml`)
- **Postman**: import YAML ke Postman untuk auto-generate collection
- **Client codegen** (opsional): `openapi-generator` untuk generate Dart model Flutter

### 6. Masih Belum Ada (Backlog)
Endpoint yang belum di-spec (sesuaikan saat dibutuhkan):
- CRUD `jenis-workorder` (Super Admin) — untuk form builder Web
- CRUD `form-template` (Super Admin) — editor field
- CRUD `pegawai` + `user` (Super Admin)
- `/dashboard/summary` per role
- Reset password

Tambahkan saat sudah siap di-develop.

---

## Referensi

- ERD Logical: `.cursor/ERD_Logical.md`
- Scope & MoSCoW: `.cursor/MoSCow.md`
- Backend ticketing: `.cursor/Implement_this_ticketing.md`
