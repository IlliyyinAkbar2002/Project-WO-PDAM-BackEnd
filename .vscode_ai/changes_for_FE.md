# Backend Changes for FE Integration

## 1. `/api/v1/detail-form` Removed (404 Fix)

The `/api/v1/detail-form` endpoint no longer exists. It was part of the legacy `DetailForm` model that has been dropped.

**What FE should do:**
- After user clicks "Selesai" button, call `POST /api/v1/progress-workorder/submit` with `tipe_progress_kode: 'SELESAI'`.
- Do NOT redirect to a page that fetches `/api/v1/detail-form`.
- After successful submit, redirect to the progress list or workorder detail page instead.

---

## 2. Submit "Selesai" Flow (Staff Completes Work)

**Endpoint:** `POST /api/v1/progress-workorder/submit`

**Request body (multipart or JSON):**
```json
{
  "workorder_id": 1,
  "tipe_progress_kode": "SELESAI",
  "hasil_pengerjaan": "Pekerjaan selesai, pipa sudah diganti",
  "latitude": -6.123456,
  "longitude": 106.123456,
  "accuracy": 10.5,
  "foto": ["(file upload, optional)"]
}
```

**Response (201):**
```json
{
  "progress": { "id": 5, "..." : "..." },
  "workorder": {
    "id": 1,
    "progres_persen": 90,
    "status": { "id": 7, "kode": "PENGECEKAN", "nama": "Pengecekan" }
  }
}
```

After this call, the workorder status becomes `PENGECEKAN` (waiting for SPV review). The FE should show a "Menunggu review SPV" state.

---

## 3. SPV Review Flow (Approve / Revisi / Tolak)

**Endpoint:** `POST /api/v1/progress-workorder/review`

**Authorization:** Only the SPV who is `assigned_to` on the workorder can review. This is the same SPV who created/owns the WO.

**Request body:**
```json
{
  "progress_id": 5,
  "decision": "accept",
  "alasan_penolakan": null,
  "field_to_revise": null
}
```

`decision` values: `"accept"`, `"revisi"`, `"tolak"`

### Decision outcomes:

| decision | WO status after | progress_workorder columns updated |
|----------|----------------|-------------------------------------|
| `accept` | `SELESAI` | `reviewed_by_user_id`, `reviewed_at`, status → VERIFIED |
| `revisi` | `IN_PROGRESS` | `reviewed_by_user_id`, `reviewed_at`, `alasan_penolakan`, `field_to_revise`, status → REVISI_REQUESTED |
| `tolak`  | `DITOLAK_SPV` | `reviewed_by_user_id`, `reviewed_at`, `alasan_penolakan`, status → DITOLAK_SPV |

### Response (200):
```json
{
  "message": "Review progress berhasil diproses",
  "workorder": {
    "id": 1,
    "status": { "id": 10, "kode": "SELESAI", "nama": "Selesai" },
    "approved_by_user_id": 1,
    "approved_at": "2026-05-17T10:00:00.000000Z"
  }
}
```

### Key change: When SPV accepts, the workorder is directly marked `SELESAI` with `approved_by_user_id` filled. No separate manager approval step needed.

---

## 4. Workorder `approved_by_user_id` Column

When SPV accepts via the review endpoint:
- `workorder.approved_by_user_id` = SPV's user ID
- `workorder.approved_at` = timestamp of approval
- `workorder.tanggal_selesai` = timestamp of completion
- `workorder.status_id` → `SELESAI`

The `approved_by_user_id` will always be the same user as `assigned_to` (the SPV who owns the WO).

---

## 5. API Routes Summary

All progress-workorder endpoints (authenticated, Bearer token required):

| Method | Path | Description |
|--------|------|-------------|
| GET | `/api/v1/progress-workorder` | List all progress (filter: `?workorder_id=X`) |
| GET | `/api/v1/progress-workorder/quota/{id}` | Get remaining submission quota for a WO |
| POST | `/api/v1/progress-workorder/manual-run` | Admin: add progress for all active WOs |
| POST/PUT/PATCH | `/api/v1/progress-workorder/start` | Staff starts work (first progress entry) |
| POST/PUT/PATCH | `/api/v1/progress-workorder/submit` | Staff submits progress or marks done |
| POST/PUT/PATCH | `/api/v1/progress-workorder/review` | SPV reviews submitted progress |
| GET | `/api/v1/progress-workorder/{id}` | Get single progress detail |
| POST/PUT/PATCH | `/api/v1/progress-workorder/{id}` | Update existing progress entry |

---

## 6. Removed / Deprecated

- `/api/v1/detail-form` — REMOVED. Do not call this endpoint.
- The manager approval step (`MENUNGGU_APPROVAL_MANAGER`) is no longer triggered by SPV review. SPV accept = final approval.
- The separate `/api/v1/workorder/{id}/approve` endpoint still exists for cases where manager approval is needed separately, but the standard flow is now: Staff submit → SPV review (accept) → Done.
