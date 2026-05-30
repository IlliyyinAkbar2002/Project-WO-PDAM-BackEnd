# Panduan Migrasi Flutter — Sinkronisasi Backend TKT-01 s/d TKT-08

Dokumen ini merangkum semua perubahan kontrak API yang harus diikuti FE
Flutter setelah ticket **TKT-01 sampai TKT-08** di-deploy, ditambah
perbaikan kecil pada `routes/api.php` yang dilakukan setelah
keseluruhan ticket tersebut merge. Fokusnya: apa yang **berubah**, apa
yang **tetap sama**, dan apa yang sekarang **tersedia sebagai bonus**.

> **Audience**: developer Flutter yang pegang aplikasi Work Order.
> **Scope backend**: commit setelah semua TKT-01..08 merge + route refactor.
> **Bahasa**: campuran ID/EN — istilah teknis dibiarkan bahasa aslinya.
> **Base URL**: semua endpoint di-prefix `/v1/` (mis. `POST /v1/workorder`,
> `POST /v1/workorder-action`). Satu-satunya endpoint tanpa prefix adalah
> health check `GET /ping`.

---

## TL;DR — Yang WAJIB Diubah di Flutter

Dua perubahan berikut bisa bikin aplikasi crash saat parsing JSON kalau
tidak disentuh:

| # | Apa yang hilang / berubah | Endpoint terdampak | Dampak Flutter |
|---|---|---|---|
| **1** | Field `tipe_progress` (String) pada progress_workorder **DIHAPUS**, diganti `tipe_progress_id` (int). | `GET /progress-workorder*`, `PUT /progress-workorder/:id` | Model Dart lama yang baca `json['tipe_progress'] as String` akan dapat `null` → NPE. |
| **2** | Field `petugas` (object) pada workorder **DIGANTI** `petugas_list` (array). | `GET /workorder`, `GET /workorder/:id`, response `POST /workorder` | Model Dart lama yang baca `json['petugas']` sebagai object akan dapat `null`. |

Selain dua itu, ada beberapa **field baru** (status progres, submitter,
actor) yang boleh diabaikan dulu, tapi sangat disarankan diadopsi biar UX
lebih kaya (detail di bagian 5).

Payload `POST /workorder` (yang paling sering ditakutkan) **tidak berubah**
— Flutter tetap kirim `petugas_id` sebagai `List<int>`.

> **Catatan route** (berlaku setelah route refactor pasca TKT-01..07):
> endpoint `POST /v1/workorder-action` **kini terdaftar resmi**.
> Sebelumnya hanya `GET /v1/workorder-action` yang ter-register — padahal
> controller `store()` sudah di-refactor untuk menginject `actor_id`.
> Flutter yang sempat kena 404/405 saat freeze/resume/extend sekarang
> aman memanggil endpoint ini. Lihat **Lampiran A** di bagian akhir untuk
> daftar lengkap endpoint yang relevan untuk mobile.

> **Catatan error handling (TKT-08)**: validator gagal **sudah tidak lagi
> muncul sebagai `500 - The given data was invalid.`** — sekarang balas
> **HTTP 422** dengan body standar Laravel `{message, errors}`. Auth gagal
> balas **401**, resource tidak ditemukan balas **404**. Update
> interceptor Flutter supaya tidak lagi melempar semua kegagalan ke
> "server error" yang sama. Detail di bagian 7 + contoh `ApiException` +
> `ApiErrorInterceptor` Dart siap pakai.

---

## 1. Changelog per Ticket (dampak ke Flutter)

| Ticket | Ringkasan Backend | Dampak Flutter | Status |
|---|---|---|---|
| TKT-01 | Tambah kolom `kode` di `m_action` (PENUGASAN/FREEZE/RESUME/EXTEND). | Response action sekarang include `"kode": "..."`. Bisa dipakai Flutter untuk branching UI (mis. badge "Freeze"). | **Aditif** — aman diabaikan. |
| TKT-02 | Tabel master `m_tipe_progress` (MULAI/PROGRESS/SELESAI). | Tidak langsung — hanya prasyarat TKT-05. | **Aditif** — tidak ada perubahan response. |
| TKT-03 | Tambah `status_id` di `progress_workorder` (DRAFT/SUBMITTED/VERIFIED). | Row progres sekarang punya `status_id` + opsional relasi `status`. Flutter bisa tampilkan state per-progress. | **Aditif** — aman diabaikan. |
| TKT-04 | Tambah `submitted_by_user_id` di `progress_workorder` (+ relasi `submitter`). | Setelah multi-petugas (TKT-07), Flutter bisa tampilkan "disubmit oleh: <nama>". | **Aditif** — aman diabaikan. |
| **TKT-05** | `tipe_progress` string → `tipe_progress_id` FK ke `m_tipe_progress`. | **BREAKING**: string `tipe_progress` hilang. Lihat TL;DR #1. | **Wajib disesuaikan.** |
| TKT-06 | Tambah `actor_id` di `workorder_action` (+ relasi `actor`). | Response workorder_action include pelaku. Flutter bisa tampilkan "di-freeze oleh: <nama>". Payload `POST /workorder-action` **jangan** kirim `actor_id` — backend override dari auth. | **Aditif** — aman diabaikan. |
| **TKT-07** | `workorder.petugas_id` (FK tunggal) → pivot `workorder_petugas` (M-to-M). | **BREAKING**: `petugas` (object) jadi `petugas_list` (array). Lihat TL;DR #2. Payload submit WO tetap `petugas_id: List<int>`. | **Wajib disesuaikan.** |
| **TKT-08** | Perbaiki `Handler::render()` — `ValidationException` & HTTP exceptions lain tidak lagi dibungkus jadi 500. | **Semi-breaking (positif)**: validator gagal sekarang balas **HTTP 422** dengan body standar Laravel `{message, errors}`. Interceptor Flutter perlu diupdate supaya tidak lagi melempar "500 - The given data was invalid.". Detail di bagian 7. | **Sebaiknya disesuaikan** untuk UX yang benar. Aman kalau ditunda — aplikasi tidak crash, cuma tidak menampilkan error per-field. |

---

## 2. Quick-Win Checklist untuk Flutter

Ini daftar minimum perubahan supaya aplikasi tidak crash setelah deploy:

- [ ] Update model `ProgressWorkorder`: ganti field `tipeProgress` (String) jadi `tipeProgressId` (int). Buat enum/const helper: `1 = MULAI, 2 = PROGRESS, 3 = SELESAI`. Hindari string literal `"Mulai"/"Selesai"` di UI — pakai enum.
- [ ] Update model `Workorder`: ganti field `petugas` (single object) jadi `petugasList` (List). Update semua widget yang render nama petugas supaya iterasi list — walaupun untuk sekarang biasanya tetap 1 elemen.
- [ ] Update response handler `POST /workorder`: sekarang `data` selalu jadi list 1 elemen (bukan N elemen). Aman untuk dicek panjangnya.
- [ ] Hapus pengiriman `actor_id` dari payload `POST /workorder-action` kalau ada (sebelumnya memang tidak di-spec, tapi beberapa implementasi mungkin ikut-ikutan kirim).
- [ ] Audit semua tempat yang compare string `== "Mulai"` / `== "Selesai"` di UI Flutter — ganti ke enum/const dari `tipe_progress_id`.

Setelah 5 item di atas beres → aplikasi akan jalan. Sisanya optional
(fitur baru, bisa diimplementasikan bertahap).

---

## 3. Before / After — JSON Response per Endpoint

### 3.1 `GET /workorder` (list) dan `GET /workorder/:id`

**Sebelum TKT-07**

```json
{
  "id": 42,
  "judul_pekerjaan": "Perbaikan pompa",
  "petugas_id": 5,
  "pic_id": 3,
  "petugas": {
    "id": 5,
    "email": "david123@gmail.com",
    "pegawai": { "id": 5, "nama": "David", "nip": "1234567894" }
  },
  "pic": { "id": 3, "email": "aulya@gmail.com", "pegawai": { ... } },
  "status": { "id": 2, "nama": "Assigned" }
}
```

**Sesudah TKT-07**

```json
{
  "id": 42,
  "judul_pekerjaan": "Perbaikan pompa",
  "pic_id": 3,
  "petugas_list": [
    {
      "id": 5,
      "email": "david123@gmail.com",
      "pegawai": { "id": 5, "nama": "David", "nip": "1234567894" },
      "pivot": {
        "workorder_id": 42,
        "user_id": 5,
        "peran": null
      }
    },
    {
      "id": 6,
      "email": "budi123@gmail.com",
      "pegawai": { "id": 6, "nama": "Budi", "nip": "1234567895" },
      "pivot": { "workorder_id": 42, "user_id": 6, "peran": null }
    }
  ],
  "pic": { "id": 3, "email": "aulya@gmail.com", "pegawai": { ... } },
  "status": { "id": 2, "nama": "Assigned" }
}
```

Hal yang hilang: `petugas_id` (FK tunggal) dan `petugas` (object).
Hal yang baru: `petugas_list` (array) — tiap elemen punya `pivot`
dengan `peran` (nullable, untuk masa depan).

---

### 3.2 `POST /workorder`

**Payload (TIDAK BERUBAH — tetap kirim `petugas_id` array)**

```json
{
  "judul_pekerjaan": "Perbaikan pompa",
  "waktu_penugasan": "2026-04-20 08:00:00",
  "estimasi_durasi": 2,
  "unit_waktu": "Jam",
  "estimasi_selesai": "2026-04-20 10:00:00",
  "longitude": 112.754074,
  "latitude": -7.2654798,
  "location_id": 1,
  "jenis_workorder_id": 3,
  "jenis_lokasi_id": 1,
  "tipe_workorder_id": 1,
  "petugas_id": [5, 6]
}
```

Field `pic_id` **jangan** dikirim dari client — backend otomatis pakai
`auth()->user()->id`.

**Response — SEKARANG SELALU 1 ELEMEN**

Sebelum: `data` bisa berisi N WO (satu per petugas) karena backend duplikat.
Sesudah: `data` selalu berisi 1 WO, dengan `petugas_list` berisi N petugas.

```json
{
  "message": "Work Order berhasil disimpan",
  "data": [
    {
      "id": 42,
      "judul_pekerjaan": "Perbaikan pompa",
      "pic_id": 3,
      "petugas_list": [ { ... }, { ... } ],
      "pic": { ... },
      "status": { "id": 2 },
      "jenis_workorder": { ... },
      "tipe_workorder": { ... },
      "lembur_spl": null
    }
  ]
}
```

> Tetap gunakan `data[0]` untuk ambil WO yang dibuat — kontrak array tidak
> berubah, hanya cardinality-nya turun dari N jadi 1.

---

### 3.3 `GET /progress-workorder?workorder_id=:id`

**Sebelum TKT-03/04/05**

```json
[
  {
    "id": 100,
    "workorder_id": 42,
    "tipe_progress": "Mulai",
    "hasil_pengerjaan": null,
    "order": 0,
    "waktu_submit": null
  },
  {
    "id": 101,
    "workorder_id": 42,
    "tipe_progress": "Selesai",
    ...
  }
]
```

**Sesudah TKT-03/04/05** (catatan: backend saat ini **belum** eager-load
relasi `tipeProgress`, `status`, dan `submitter` di endpoint ini — lihat
sub-bagian 6 "Known Gap")

```json
[
  {
    "id": 100,
    "workorder_id": 42,
    "tipe_progress_id": 1,
    "hasil_pengerjaan": null,
    "status_id": 9,
    "submitted_by_user_id": null,
    "order": 0,
    "waktu_submit": null,
    "dokumentasi_progress": [],
    "detail_progress": []
  }
]
```

Untuk UI, gunakan konstanta berikut di sisi Flutter (nilai `id` stabil —
dipaksa eksplisit lewat migration/seeder):

```dart
class TipeProgressId {
  static const int mulai = 1;
  static const int progress = 2;
  static const int selesai = 3;
}

class ProgressStatusId {
  static const int draft = 9;
  static const int submitted = 10;
  static const int verified = 11;
}
```

> Kalau nanti backend menambah eager-load `tipe_progress`, `status`, dan
> `submitter` (lihat bagian 6), Flutter bisa beralih ke object form tanpa
> mengubah konstanta di atas.

---

### 3.4 `PUT /progress-workorder/:id` (submit progres)

**Payload (TIDAK BERUBAH)**

Tetap `multipart/form-data` dengan:

- `hasil_pengerjaan` (string)
- `waktu_submit` (date)
- `foto[]` (array file, min 1)
- `detail_progress[]` (hanya untuk progres bertipe SELESAI)
- `detail_progress_images[detail_form_id]` (hanya untuk field bertipe `image`)

Backend sekarang memvalidasi "apakah progres ini tipe SELESAI?" lewat
relasi `tipeProgress.kode === 'SELESAI'` alih-alih string literal. Flutter
tidak perlu berubah karena pembedaan ini server-side.

**Yang berubah di backend**:

- `submitted_by_user_id` otomatis diisi dari `auth()->user()->id` — FE **tidak usah** kirim field ini.
- Setelah submit, row progres status_id berubah DRAFT → SUBMITTED otomatis.
- Status workorder tetap bertransisi seperti sebelumnya (status 7 saat "Mulai" disubmit, status 5 saat "Selesai" disubmit).

**Response** tetap berisi object `ProgressWorkorder` dengan `detail_progress`
dan `dokumentasi_progress`. Field tambahan: `status_id`, `submitted_by_user_id`.

---

### 3.5 `POST /workorder-action` (freeze / resume / extend)

**Payload (TIDAK BERUBAH secara shape — tapi ada satu field yang jangan dikirim)**

```json
{
  "workorder_id": 42,
  "action_id": 2,
  "keterangan": "Menunggu sparepart",
  "waktu_mulai": "2026-04-20 14:00:00"
}
```

**JANGAN** kirim `actor_id` di body — backend **mengabaikannya**
(whitelist validator) dan **selalu override** dengan `auth()->user()->id`.
Kalau Flutter terlanjur kirim, tidak error, cuma jadi no-op.

**Tips**: masih aman kirim `action_id` (id numerik master), tapi FE
disarankan kelak migrasi ke `kode` string (PENUGASAN/FREEZE/RESUME/EXTEND)
bila backend nanti menambah endpoint / response yang expose `kode`.

**Response**: sekarang juga mengembalikan `actor_id` di record yang
dibuat. Bila FE mau tampilkan pelaku, minta backend eager-load relasi
`actor` di response show/list workorder_action (belum dilakukan).

---

## 4. Contoh Diff Model Dart

### 4.1 `Workorder` — dari single `petugas` ke `petugasList`

**Sebelum**

```dart
class Workorder {
  final int id;
  final String judulPekerjaan;
  final int picId;
  final int petugasId;
  final User? petugas;
  final User? pic;

  factory Workorder.fromJson(Map<String, dynamic> json) => Workorder(
        id: json['id'] as int,
        judulPekerjaan: json['judul_pekerjaan'] as String,
        picId: json['pic_id'] as int,
        petugasId: json['petugas_id'] as int,
        petugas: json['petugas'] != null
            ? User.fromJson(json['petugas'])
            : null,
        pic: json['pic'] != null ? User.fromJson(json['pic']) : null,
      );
}
```

**Sesudah**

```dart
class Workorder {
  final int id;
  final String judulPekerjaan;
  final int picId;
  final List<WorkorderPetugas> petugasList;
  final User? pic;

  factory Workorder.fromJson(Map<String, dynamic> json) => Workorder(
        id: json['id'] as int,
        judulPekerjaan: json['judul_pekerjaan'] as String,
        picId: json['pic_id'] as int,
        petugasList: ((json['petugas_list'] as List?) ?? const [])
            .map((e) => WorkorderPetugas.fromJson(e as Map<String, dynamic>))
            .toList(growable: false),
        pic: json['pic'] != null ? User.fromJson(json['pic']) : null,
      );
}

class WorkorderPetugas {
  final int userId;
  final String? email;
  final Pegawai? pegawai;
  final String? peran;

  factory WorkorderPetugas.fromJson(Map<String, dynamic> json) {
    final pivot = (json['pivot'] as Map<String, dynamic>?) ?? const {};
    return WorkorderPetugas(
      userId: json['id'] as int,
      email: json['email'] as String?,
      pegawai: json['pegawai'] != null
          ? Pegawai.fromJson(json['pegawai'])
          : null,
      peran: pivot['peran'] as String?,
    );
  }
}
```

**Helper untuk render list petugas di UI**:

```dart
String formatPetugasNames(List<WorkorderPetugas> list) {
  final names = list
      .map((e) => e.pegawai?.nama ?? '—')
      .where((n) => n.isNotEmpty)
      .toList();
  if (names.isEmpty) return '—';
  if (names.length == 1) return names.first;
  if (names.length <= 3) return names.join(', ');
  return '${names.take(2).join(', ')} +${names.length - 2}';
}
```

---

### 4.2 `ProgressWorkorder` — dari `tipeProgress` string ke FK

**Sebelum**

```dart
class ProgressWorkorder {
  final int id;
  final int workorderId;
  final String tipeProgress;
  final String? hasilPengerjaan;
  final int order;
  final DateTime? waktuSubmit;

  bool get isMulai => tipeProgress == 'Mulai';
  bool get isSelesai => tipeProgress == 'Selesai';

  factory ProgressWorkorder.fromJson(Map<String, dynamic> json) =>
      ProgressWorkorder(
        id: json['id'] as int,
        workorderId: json['workorder_id'] as int,
        tipeProgress: json['tipe_progress'] as String,
        ...
      );
}
```

**Sesudah**

```dart
class ProgressWorkorder {
  final int id;
  final int workorderId;
  final int tipeProgressId;
  final int? statusId;
  final int? submittedByUserId;
  final String? hasilPengerjaan;
  final int order;
  final DateTime? waktuSubmit;

  bool get isMulai => tipeProgressId == TipeProgressId.mulai;
  bool get isSelesai => tipeProgressId == TipeProgressId.selesai;
  bool get isProgress => tipeProgressId == TipeProgressId.progress;

  bool get isDraft => statusId == ProgressStatusId.draft;
  bool get isSubmitted => statusId == ProgressStatusId.submitted;

  String get label {
    switch (tipeProgressId) {
      case TipeProgressId.mulai:
        return 'Mulai';
      case TipeProgressId.selesai:
        return 'Selesai';
      default:
        return 'Progress ${order}';
    }
  }

  factory ProgressWorkorder.fromJson(Map<String, dynamic> json) =>
      ProgressWorkorder(
        id: json['id'] as int,
        workorderId: json['workorder_id'] as int,
        tipeProgressId: json['tipe_progress_id'] as int,
        statusId: json['status_id'] as int?,
        submittedByUserId: json['submitted_by_user_id'] as int?,
        hasilPengerjaan: json['hasil_pengerjaan'] as String?,
        order: json['order'] as int,
        waktuSubmit: json['waktu_submit'] != null
            ? DateTime.parse(json['waktu_submit'])
            : null,
      );
}
```

---

## 5. Field Baru yang Bisa Dimanfaatkan (Optional)

Berikut field opsional yang sekarang tersedia — mengadopsinya akan bikin
UI lebih informatif tanpa memaksa migrasi breaking:

| Field | Asal | Contoh penggunaan UI |
|---|---|---|
| `progress_workorder.status_id` | TKT-03 | Badge "Draft" / "Submitted" / "Verified" pada tiap row progres di detail WO. |
| `progress_workorder.submitted_by_user_id` | TKT-04 | Label "disubmit oleh @nama" di history — relevan ketika multi-petugas (TKT-07). |
| `workorder_action.actor_id` | TKT-06 | Timeline "di-freeze oleh @nama pada 20 Apr 14:00". |
| `m_action.kode` | TKT-01 | Logic branching yang stabil — tidak bergantung id numerik. |
| `workorder.petugas_list[*].pivot.peran` | TKT-07 | Placeholder untuk future feature "koordinator vs anggota". Nullable sekarang. |

Minta backend untuk tambah `->with('actor', 'status', 'submitter', 'tipeProgress')` di endpoint terkait kalau ingin object form langsung masuk response (bukan hanya id numerik).

---

## 6. Known Gap & Follow-up yang Disarankan

Beberapa gap eager-loading di controller masih ada. Backend **belum**
memuat relasi berikut di response default:

| Relasi | Endpoint | Alasan belum | Saran |
|---|---|---|---|
| `progressWorkorder.tipeProgress` | `GET /progress-workorder*` | Scope TKT-05 hanya mengganti kolom DB, tidak menyentuh eager-load. | Buka PR kecil `->with('tipeProgress')` di `ProgressWorkorderController::index` & `show`. |
| `progressWorkorder.status` | sda | sda (TKT-03 hanya tambah FK). | sda untuk `->with('status')`. |
| `progressWorkorder.submitter` | sda | sda (TKT-04). | sda untuk `->with('submitter')`. |
| `workorderAction.actor` | `GET /workorder/:id` (via relasi `workorderAction`) | TKT-06 hanya menambah FK. | Tambah `->with('workorderAction.actor')` kalau timeline WO mau include pelaku. |

Sampai PR tersebut di-merge, Flutter bisa:

- **Short-term**: pakai konstanta id (seperti `TipeProgressId.mulai = 1`) untuk membandingkan — aman karena id di master data dipaksa eksplisit.
- **Long-term**: minta backend expose relasi di atas, lalu refactor model Dart untuk baca object.

### Gap yang SUDAH dibereskan

- ~~`POST /workorder-action` belum terdaftar di route (padahal controller siap)~~ → **sudah diperbaiki**. Endpoint ini sekarang aktif dan Flutter boleh memanggilnya tanpa takut 404/405.

---

## 7. Error Handling — Kontrak Error Baru (TKT-08)

**Status sebelum TKT-08**: override `Handler::render()` membungkus **semua**
exception jadi HTTP 500 dengan body `{"success": false, "message": "..."}`.
Validator gagal pun muncul di chat Flutter sebagai
`500 - The given data was invalid.` — tidak bisa dibedakan dari error
server sungguhan, dan body standar Laravel `{message, errors}` hilang.

**Status sekarang (TKT-08 merged)**: hanya exception generik yang
dibungkus 500. Exception yang sudah punya makna HTTP didelegasikan ke
renderer default Laravel → status + body sesuai konvensi.

### 7.1 Tabel kode error yang Flutter harus tangani

| HTTP | Kondisi | Body response | Catatan Flutter |
|---|---|---|---|
| **422** | Validator gagal (mis. field required kosong, format salah, `petugas_id` bukan array). | `{"message": "The given data was invalid.", "errors": {"field": ["pesan..."]}}` | Parse `errors` per field → tampilkan di form sebagai inline error. |
| **401** | Token tidak valid / tidak dikirim. | `{"message": "Unauthenticated."}` | Redirect ke login screen. Token lama invalid. |
| **403** | Token valid tapi tidak berhak (otorisasi gagal, mis. Staff coba akses endpoint admin). | `{"message": "This action is unauthorized."}` | Tampilkan pesan generic "Kamu tidak punya akses untuk aksi ini." |
| **404** | Resource tidak ditemukan (`findOrFail` dst.). | `{"message": "No query results for model [App\\Models\\X] ..."}` | Tampilkan "Data tidak ditemukan". **Pesan internal jangan ditampilkan mentah** — bisa bocorkan nama model. |
| **405** | Method tidak diizinkan (mis. panggil POST ke route GET-only). | `{"message": "The <METHOD> method is not supported..."}` | Biasanya bug di FE — cek method + path. |
| **429** | Throttle / rate limit terlampaui. | `{"message": "Too Many Attempts."}` | Implementasi retry dengan backoff. |
| **500** | Genuine server error (bug, DB down, FK violation tak terduga). | `{"success": false, "message": "<pesan exception>"}` | Tampilkan pesan generic ke user + log ke Sentry/Crashlytics. Backend juga sudah auto-log ke `laravel.log`. |

### 7.2 Contoh body 422 (paling sering dipakai)

Untuk `POST /v1/workorder` tanpa field wajib:

```json
{
  "message": "The given data was invalid.",
  "errors": {
    "judul_pekerjaan": ["The judul pekerjaan field is required."],
    "estimasi_durasi": ["The estimasi durasi field is required."],
    "petugas_id": ["The petugas id field is required."]
  }
}
```

Untuk `POST /v1/workorder` dengan `petugas_id` dikirim sebagai integer
(bukan array):

```json
{
  "message": "The given data was invalid.",
  "errors": {
    "petugas_id": ["The petugas id must be an array."]
  }
}
```

Key di `errors` adalah **nama field yang sama seperti di payload** — FE
bisa mapping 1:1 ke controller form.

### 7.3 Contoh Dart: interceptor + parser

Untuk project yang pakai `dio`:

```dart
class ApiException implements Exception {
  final int statusCode;
  final String message;
  final Map<String, List<String>> fieldErrors;

  ApiException({
    required this.statusCode,
    required this.message,
    this.fieldErrors = const {},
  });

  bool get isValidation => statusCode == 422;
  bool get isUnauthenticated => statusCode == 401;
  bool get isForbidden => statusCode == 403;
  bool get isNotFound => statusCode == 404;
  bool get isServerError => statusCode >= 500;

  /// Ambil error pertama untuk field tertentu (cocok untuk inline error di TextField).
  String? errorFor(String field) => fieldErrors[field]?.first;
}

class ApiErrorInterceptor extends Interceptor {
  @override
  void onError(DioException err, ErrorInterceptorHandler handler) {
    final response = err.response;
    if (response == null) {
      handler.next(err);
      return;
    }

    final data = response.data;
    final status = response.statusCode ?? 0;

    // Parse body standar Laravel: {message, errors?}.
    String message = 'Terjadi kesalahan';
    Map<String, List<String>> fieldErrors = const {};

    if (data is Map) {
      message = (data['message'] as String?) ?? message;

      final rawErrors = data['errors'];
      if (rawErrors is Map) {
        fieldErrors = rawErrors.map(
          (key, value) => MapEntry(
            key.toString(),
            (value as List).map((e) => e.toString()).toList(),
          ),
        );
      }
    }

    handler.reject(
      DioException(
        requestOptions: err.requestOptions,
        error: ApiException(
          statusCode: status,
          message: message,
          fieldErrors: fieldErrors,
        ),
        type: err.type,
        response: response,
      ),
    );
  }
}
```

Penggunaan di form submit:

```dart
try {
  await api.post('/v1/workorder', data: payload);
} on DioException catch (err) {
  final ex = err.error;
  if (ex is ApiException && ex.isValidation) {
    setState(() {
      _errorJudul = ex.errorFor('judul_pekerjaan');
      _errorPetugas = ex.errorFor('petugas_id');
      // ... dst per field
    });
  } else if (ex is ApiException && ex.isUnauthenticated) {
    context.go('/login');
  } else {
    showSnack('Gagal menyimpan. Coba lagi.');
  }
}
```

### 7.4 Field internal yang TIDAK boleh Flutter andalkan

- **`success`** — backend hanya mengirim `success: false` untuk HTTP 500 (wrapper lama yang sekarang dipakai untuk exception generik). Response 422/401/403/404 **tidak** membawa field `success`. Jangan pakai `success` sebagai indikator — gunakan **status code HTTP** sebagai source of truth.
- **`errors`** hanya muncul pada 422. Untuk 500, ini selalu absen.
- **`message`** pada 500 bisa berisi stack trace technical — **jangan** ditampilkan ke user end tanpa sanitasi. Cukup tampilkan "Terjadi kesalahan di server".

---

## 8. Testing Checklist di Flutter

Skenario minimum yang wajib lulus setelah migrasi:

### List & Detail WO

- [ ] Buka halaman daftar WO sebagai Admin → semua WO tampil, termasuk yang multi-petugas (nama ditampilkan benar, tidak `null`).
- [ ] Buka halaman daftar WO sebagai Staff yang di-assign ke 1+ WO → WO yang ditugaskan ke dia muncul.
- [ ] Buka halaman daftar WO sebagai SPV yang membuat WO → WO yang dia buat muncul.
- [ ] Buka detail WO → nama semua petugas (bila lebih dari 1) tampil di bagian "Petugas".

### Create WO

- [ ] Submit form "Ajukan" dengan 1 petugas → berhasil; `data.length == 1`; WO muncul di list.
- [ ] Submit form "Ajukan" dengan 3 petugas → berhasil; `data.length == 1` (bukan 3); detail WO menunjukkan 3 petugas di `petugas_list`.
- [ ] Submit form "Ajukan" dengan petugas duplikat (misal id 5 dua kali) → tidak error di server; pivot hanya berisi 1 baris unik.
- [ ] Tipe workorder SPL (lembur, `tipe_workorder_id = 2`) → WO berhasil dibuat tapi belum ada progres awal (sesuai behavior backend: progres & PENUGASAN hanya di-spawn setelah SPL di-approve).

### Progress flow (bagian yang paling rawan TKT-05)

- [ ] Buka detail WO → ada row progres "Mulai" dan "Selesai" (dibuat otomatis saat WO dibuat).
- [ ] Submit progres Mulai → status WO berubah ke "In Progress" (status_id=7).
- [ ] Tambah progres antara via button "+" → row baru muncul di posisi sebelum "Selesai"; `order` konsisten.
- [ ] Submit progres Selesai → status WO berubah ke "Selesai" (status_id=5).
- [ ] Semua label UI progres (Mulai / Progress N / Selesai) tampil dari `tipeProgressId` — tidak ada yang kosong.

### Workorder action (freeze/resume/extend)

- [ ] Kirim `POST /workorder-action` dengan `action_id` FREEZE tanpa `actor_id` di body → sukses; record terbuat dengan `actor_id` = user yang login.
- [ ] Kirim dengan `actor_id` di body yang berbeda dari user login → `actor_id` di DB tetap = user login (backend override).

### Error handling (TKT-08)

- [ ] Submit `POST /workorder` tanpa `judul_pekerjaan` → terima **HTTP 422** (bukan 500) dengan body `{message, errors: {judul_pekerjaan: [...]}}`.
- [ ] Submit `POST /workorder` dengan `petugas_id: 5` (integer, bukan array) → **HTTP 422** dengan error di field `petugas_id`.
- [ ] Panggil endpoint protected tanpa Bearer token → **HTTP 401** dengan body `{message: "Unauthenticated."}`. Tidak ada 500.
- [ ] `GET /workorder/99999` (id tidak ada) → **HTTP 404** (bukan 500).
- [ ] Form yang pakai interceptor baru — inline error muncul di TextField tepat, tidak ada dialog generic "The given data was invalid."
- [ ] Genuine server error (mis. simulasikan DB koneksi putus di environment dev) → **HTTP 500** dengan body `{success: false, message: ...}` dan log muncul di `storage/logs/laravel.log` dengan tag `Unhandled exception → JSON 500`.

### Regressions umum

- [ ] Tidak ada UI yang menampilkan `null` di tempat yang sebelumnya ada nama petugas.
- [ ] Tidak ada UI yang menampilkan `null` / `"Mulai"` / `"Selesai"` sebagai string literal di tempat yang sebelumnya menggunakan `tipe_progress`.
- [ ] Semua unit test yang mock response WO / progres sudah diadaptasi ke shape baru.

---

## 9. FAQ

**Q: Apakah Flutter wajib kirim `peran` saat assign petugas?**
A: Tidak. Field `peran` di pivot nullable dan belum ada di payload
`POST /workorder`. Nanti bila dibutuhkan (fase berikutnya), backend akan
menambah opsi struktur payload (mis. `petugas: [{user_id, peran}]`) tanpa
memutuskan shape lama — kita akan buat ticket terpisah.

**Q: Apa yang terjadi kalau saya tetap kirim `petugas_id` sebagai integer (bukan array)?**
A: Validator akan menolak dengan error (backend hanya terima
`petugas_id` sebagai array dengan minimal 1 elemen). Pastikan Flutter
selalu bungkus `[5]` bahkan untuk kasus single petugas.

**Q: Apakah `pic_id` perlu dikirim?**
A: Tidak. Backend selalu set `pic_id = auth()->user()->id` untuk alasan
keamanan (SPV tidak boleh mengaku-ngaku sebagai SPV lain).

**Q: Apakah `actor_id` pada workorder_action terisi untuk data lama?**
A: Tidak — kolom `actor_id` nullable dan row yang dibuat sebelum TKT-06
tidak di-backfill (tidak ada sumber kebenaran). Flutter harus menangani
null di timeline history (tampilkan "—" atau "Tidak diketahui").

**Q: Kapan kolom `tipe_progress` string dihapus dari DB?**
A: Sudah — pada migration TKT-05 (`2026_04_19_120400`). Setelah migration
tersebut jalan, kolom hilang. Tidak ada dual-write period, jadi FE harus
siap sebelum migration ini ter-deploy ke production.

---

## 10. Ringkasan Satu Halaman

- **Base URL**: semua endpoint di `/v1/` (kecuali `GET /ping`).
- **Auth**: semua endpoint non-auth wajib header `Authorization: Bearer <token>` (Sanctum).
- **Payload** `POST /v1/workorder`: tetap (petugas_id tetap array of int).
- **Payload** `POST /v1/workorder-action`: jangan kirim `actor_id`.
- **Payload** `PUT /v1/progress-workorder/:id`: tetap (jangan kirim `submitted_by_user_id`).
- **Response** `workorder.petugas` (object) → `workorder.petugas_list` (array).
- **Response** `progress_workorder.tipe_progress` (string) → `tipe_progress_id` (int).
- **Response** beberapa field baru boleh diabaikan: `status_id`, `submitted_by_user_id`, `actor_id`, `m_action.kode`.
- **Validasi 500 → 422**: **selesai (TKT-08)**. Body error sekarang `{message, errors}`. Parser Flutter perlu update.
- **Auth gagal**: kini 401 (sebelumnya 500). Interceptor Flutter harus redirect ke login untuk 401.
- **Resource 404**: kini 404 (sebelumnya 500).
- **Route baru**: `POST /v1/workorder-action` resmi terdaftar setelah refactor `routes/api.php` pasca TKT-01..07.

Pertanyaan / clarifikasi: ping backend dev via chat; sertakan endpoint
yang kena + payload contoh + response yang kamu terima.

---

## Lampiran A — API Endpoint Reference untuk Mobile

Tabel di bawah merangkum semua endpoint yang **relevan untuk Flutter**.
Endpoint admin-only (`admin/pegawai/*`, KPI, CRUD jenis-workorder /
form-workorder / detail-form) sengaja **tidak** dimasukkan — itu ranah Web
(Next.js) dan mobile tidak perlu tahu.

Semua path di bawah sudah termasuk prefix `/v1/`. Semua butuh header
`Authorization: Bearer <token>` kecuali yang bertanda **publik**.

### A.1 Auth

| Method | Path | Keperluan | Catatan |
|---|---|---|---|
| POST | `/v1/auth/login` | Publik | Balasan berisi token Sanctum. |
| POST | `/v1/auth/register` | Publik | Self-register; field pegawai lengkap di-assign oleh admin lewat Web. |
| POST | `/v1/auth/logout` | Protected | Revoke token aktif. |
| GET  | `/v1/auth/me` | Protected | Ambil profil user + relasi pegawai. |

### A.2 Workorder (core)

| Method | Path | Keperluan | Catatan |
|---|---|---|---|
| GET | `/v1/workorder` | List WO untuk user (paginated) | Query param: `type`, `search`, `status`, `exclude_status`, `pic_id`, `user_id`, `date_range`, `start_date`, `end_date`, `page`, `limit`, `sort`, `all`. Response berisi `petugas_list` (array). |
| GET | `/v1/workorder/:id` | Detail WO | Include `petugas_list`, `pic`, `jenis_workorder`, `status`, `lembur_spl`, `latest_freeze`, `location`. |
| POST | `/v1/workorder` | Ajukan WO baru | Payload: `judul_pekerjaan`, `waktu_penugasan`, `estimasi_durasi`, `unit_waktu`, `estimasi_selesai`, `longitude?`, `latitude?`, `location_id?`, `jenis_workorder_id`, `jenis_lokasi_id`, `tipe_workorder_id`, `petugas_id: int[]`. **Jangan** kirim `pic_id` — backend override dari auth. Response: `data` array 1 elemen. |
| PUT | `/v1/workorder/:id` | Update WO | Saat ini hanya terima `estimasi_selesai` & `status_id`. Untuk kebutuhan lain, koordinasi dulu dengan backend. |

### A.3 Progress Workorder (paling sering dipakai mobile)

| Method | Path | Keperluan | Catatan |
|---|---|---|---|
| GET | `/v1/progress-workorder?workorder_id=:id` | List progres satu WO | Urut berdasarkan `order` asc. Setiap row punya `tipe_progress_id`, `status_id`, `submitted_by_user_id` (setelah TKT-03/04/05). |
| GET | `/v1/progress-workorder/:id` | Detail satu progres | Include `dokumentasi_progress`, `detail_progress`. |
| PUT | `/v1/progress-workorder/:id` | **Submit progres** (endpoint inti mobile) | `multipart/form-data` dengan `hasil_pengerjaan`, `waktu_submit`, `foto[]`, dan (khusus progres SELESAI) `detail_progress[]` + `detail_progress_images[detail_form_id]`. `submitted_by_user_id` diinject backend. |
| POST | `/v1/progress-workorder/manual-run` | Tambah "Progress N" antara | Biasanya dipanggil otomatis untuk WO berstatus 7 (In Progress). |

### A.4 Workorder Action (freeze / resume / extend) — **TKT-06**

| Method | Path | Keperluan | Catatan |
|---|---|---|---|
| GET | `/v1/workorder-action` | List action (ada, tapi controller `index` masih skeleton — minta backend implement saat butuh). | — |
| POST | `/v1/workorder-action` | **Freeze / Resume / Extend** | Payload: `workorder_id`, `action_id`, `keterangan?`, `waktu_mulai`, `sisa_durasi_menit?`, `estimasi_selesai?`. **Jangan** kirim `actor_id` (override server). |

> `action_id` untuk sekarang tetap berbasis id numerik master action.
> `kode` (PENUGASAN/FREEZE/RESUME/EXTEND) dari TKT-01 belum dipakai di
> endpoint ini sebagai identifier input — hanya ter-expose di response
> untuk branching UI.

### A.5 Lembur SPL

| Method | Path | Keperluan | Catatan |
|---|---|---|---|
| GET | `/v1/lembur-spl` | List lembur | — |
| GET | `/v1/lembur-spl/:id` | Detail | — |
| POST | `/v1/lembur-spl` | Ajukan lembur | Dipakai mobile saat Staff ajukan dari lapangan. |
| PUT | `/v1/lembur-spl/:id` | Approval (biasanya Web SPV) | Saat di-approve dengan status_id=2, backend auto-spawn progres + action PENUGASAN untuk WO terkait. |

### A.6 Master Data (untuk dropdown / picker)

| Method | Path | Keperluan | Catatan |
|---|---|---|---|
| GET | `/v1/jenis-workorder` | List jenis WO | Pakai untuk dropdown saat Ajukan. |
| GET | `/v1/jenis-workorder/:id` | Detail | Termasuk `detail_form` (definisi field dinamis untuk progres SELESAI). |
| GET | `/v1/jenis-lokasi` | List jenis lokasi | — |
| GET | `/v1/user` | List user | Pakai saat SPV pilih petugas. |
| GET | `/v1/pegawai` | List pegawai | — |
| GET | `/v1/pegawai/filter` | Filter pegawai | Endpoint khusus dengan query param (tanya backend untuk spesifikasi). |
| GET | `/v1/master-location` | List lokasi master | Geofencing. |
| POST | `/v1/master-location` | Tambah lokasi master | Kalau mobile memang bisa add (biasanya admin via Web). |

### A.7 Material

| Method | Path | Keperluan | Catatan |
|---|---|---|---|
| GET | `/v1/material` | List stok material | — |
| GET | `/v1/material/:material` | Detail material | Parameter default dari `apiResource`. |
| PATCH | `/v1/material/:kode_material/pakai` | Konsumsi material saat submit progres | Parameter berbasis `kode_material` (bukan id). |
| PUT | `/v1/material/:kode_material/edit` | Edit material (biasanya Web) | sda. |

### A.8 Health Check

| Method | Path | Keperluan | Catatan |
|---|---|---|---|
| GET | `/ping` | Publik (tanpa auth & tanpa prefix `/v1`) | Cek konektivitas backend. |

---

### Catatan singkat pasca refactor `routes/api.php` dan TKT-08

Perubahan yang terjadi di sisi backend (tidak mempengaruhi payload/response
selain yang sudah disebutkan di bab-bab sebelumnya):

1. **Route baru**: `POST /v1/workorder-action` kini terdaftar (sebelumnya hanya `GET`). Aktifkan ulang code path freeze/resume/extend di Flutter jika sebelumnya di-disable karena 404.
2. **Duplikasi dibersihkan**: 7 route yang sebelumnya ditulis ganda (sudah tercover `apiResource`) dihapus. Tidak ada perubahan URL/behavior — hanya `php artisan route:list` yang lebih bersih.
3. **Grouping ulang**: komentar di `routes/api.php` kini menandai setiap section dengan `[M]` (mobile), `[W]` (web), atau `[S]` (shared) agar reviewer lebih cepat paham.
4. **Exception handler diperbaiki (TKT-08)**: `ValidationException` kini 422, `AuthenticationException` 401, `ModelNotFoundException` 404. Interceptor error di Flutter perlu update — lihat bagian 7 untuk contoh `ApiErrorInterceptor` + `ApiException`.

Kalau menemukan endpoint yang dipakai mobile tapi tidak ada di tabel ini,
kemungkinan endpoint tersebut ada di sisi Web-only — koordinasi dulu
dengan backend sebelum menambahkan konsumsinya di Flutter.
