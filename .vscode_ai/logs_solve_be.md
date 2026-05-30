Now I need to remove the `'reviewed_by_user_id' => $userId` writes to `progress_workorder` in `ProgressDetailController`:

Now let's verify there are no remaining references to `reviewed_by_user_id` on `progress_workorder` in the app code:

All remaining references are writes to `progress_detail` (which is correct — that's where the column lives). The `ProgressDetail` model has the `reviewer()` relation pointing to its own `reviewed_by_user_id` column. The `ProgressWorkorderController` creates `ProgressDetail` rows with `reviewed_by_user_id`. All correct.

Now let's run syntax checks on all modified files:

Let me do a final verification — read the migration file to make sure it's coherent end-to-end:

Everything is clean. Let me also quickly verify the `ProgressWorkorder` model no longer has any reference to the dropped columns:

All done. Here's the complete summary of what was changed:

---

**Problem**: `reviewed_by_user_id` was duplicated in both `progress_workorder` and `progress_detail`. Per your design, all review-related columns belong exclusively in `progress_detail`.

**Changes made:**

| File | What changed |
|------|-------------|
| `database/migrations/2026_05_21_100000_...` | Now drops **4 columns** (added `reviewed_by_user_id` with `dropConstrainedForeignId` to properly remove the FK constraint). Backfill still copies `reviewed_by_user_id` into `progress_detail` before dropping. `down()` recreates all 4 columns. |
| `app/Models/ProgressWorkorder.php` | Removed `reviewer()` relationship (no longer has the FK column). Removed `'field_to_revise' => 'array'` cast. |
| `app/Http/Controllers/ProgressWorkorderController.php` | `review()` no longer writes `reviewed_by_user_id` to `progress_workorder`. It only sets `status_id` on `progress_workorder`; all review data goes into `ProgressDetail::create(...)`. |
| `app/Http/Controllers/ProgressDetailController.php` | `approve()` and `reject()` no longer write `reviewed_by_user_id` to `progress_workorder`. Only `status_id` is updated on `progress_workorder`. |

**After migration, `progress_workorder` will have these columns removed:**
- `reviewed_by_user_id` (+ its FK constraint)
- `reviewed_at`
- `alasan_penolakan`
- `field_to_revise`

**All review data now lives exclusively in `progress_detail`** — no duplication. Run `php artisan migrate` when ready.


====================================

Routes are all registered correctly. Let me now read the final state of both controllers to give you a clear API reference:

Everything is consistent. Here's the complete API route reference for your front end after migration:

---

## API Routes — Progress Detail (all stored in `progress_detail` table)

All routes are under `POST /api/v1/...` and require `Authorization: Bearer {token}`.

---

### 1. SPV Review via legacy endpoint

```
POST|PUT|PATCH  /api/v1/progress-workorder/review
```

**Payload:**
```json
{
  "progress_id": 123,
  "decision": "accept" | "revisi" | "tolak",
  "alasan_penolakan": "Foto kurang jelas",       // nullable, for revisi/tolak
  "field_to_revise": ["photo", "description"]     // nullable array, for revisi
}
```

**What happens:** Creates a `progress_detail` row with `status = approved|rejected`, `reviewed_by_user_id`, `reviewed_at`, `alasan_penolakan`, `field_to_revise` (comma-separated). Updates `progress_workorder.status_id` only.

---

### 2. SPV Approve via progress-detail endpoint

```
POST  /api/v1/progress-detail/{id}/approve
```

**Payload:** (empty body — user ID taken from auth token)

**What happens:** Updates the `progress_detail` row: `status='approved'`, `reviewed_by_user_id`, `reviewed_at`. Sets `progress_workorder.status_id = VERIFIED`.

---

### 3. SPV Reject via progress-detail endpoint

```
POST  /api/v1/progress-detail/{id}/reject
```

**Payload:**
```json
{
  "alasan_penolakan": "Foto kurang jelas, deskripsi tidak lengkap",
  "field_to_revise": "photo,description"
}
```

**What happens:** Updates the `progress_detail` row: `status='rejected'`, `reviewed_by_user_id`, `reviewed_at`, `alasan_penolakan`, `field_to_revise`. Sets `progress_workorder.status_id = REVISI_REQUESTED`.

---

### 4. Staff Resubmit after rejection

```
POST  /api/v1/progress-detail/resubmit
```

**Payload:**
```json
{
  "progress_workorder_id": 123
}
```

**What happens:** Creates a new `progress_detail` row with `status='pending'`. Sets `progress_workorder.status_id = SUBMITTED`.

---

### 5. List review history

```
GET  /api/v1/progress-detail?progress_workorder_id=123
```

**Response:** Array of `progress_detail` rows (ordered by `created_at desc`) with `reviewer` relation eager-loaded.

---

### 6. Show single detail

```
GET  /api/v1/progress-detail/{id}
```

---

### Data flow summary

```
FE payload → API route → ProgressDetail (table: progress_detail)
                        ↳ progress_workorder.status_id updated (no review columns)
```

After migration, `progress_workorder` will only have: `id`, `workorder_id`, `tipe_progress_id`, `status_id`, `submitted_by_user_id`, `hasil_pengerjaan`, `waktu_submit`, `order`, `latitude`, `longitude`, `accuracy`, `created_at`, `updated_at`. All review data lives exclusively in `progress_detail`.