# Backend Task: Milestone-Based Progress ("Tahapan") — Decouple Progress % from Reporting Limit

## Context

Laravel 8 + PostgreSQL backend serving two frontends: a Flutter mobile app (actively developed) and a Next.js web dashboard (**frozen — must not break**). Current state:

- Work-order progress percentage is derived from a *reporting quota*: `progres_persen ≈ submissions / (estimasi_hari × 8)`. See `app/Support/WorkorderQuota.php` (`SUBMISSIONS_PER_DAY = 8`, `quotaTotal()`) and `ProgressWorkorderController.php:1324-1326` (`memberSummary()` percentage).
- This conflates **reporting frequency** with **work completion** and has been academically rejected. The fix: progress must be **milestone-based**.

## Locked Design (do not re-litigate)

- Four fixed, generic phases, stored as a plain integer `1..4`. **No master table, no FK, no seeder.**
  - `1 = Persiapan`, `2 = Pengerjaan`, `3 = Pengujian`, `4 = Dokumentasi`
- Per-jenis-pekerjaan step labels are a **Flutter-side constant map only**. The backend never stores or returns labels beyond the generic four.
- New nullable column `tahapan` (`unsignedTinyInteger`) on `progress_workorder`.
- WO-level progress = `max(tahapan among countable submissions) / 4 × 100`.
- The existing quota mechanism is **demoted to a pure daily rate limiter**. It must keep working exactly as-is, but it is no longer the source of any progress percentage for WOs that have tahapan data.
- Naming rule: **no new identifier, route, JSON key, or comment may contain the word "quota"/"kuota"**. Use `tahapan` / `batas pelaporan` terminology in new code. (Existing identifiers stay untouched — see constraints.)

## Phase 1 — Analysis (report findings BEFORE writing any code)

1. Locate where the `progres_persen` attribute on `Workorder` is computed (expected: an accessor in `app/Models/Workorder.php`; see refresh comments at `ProgressWorkorderController.php:313` and `:513`). Document its exact current formula and **every** response that exposes it.
2. Map how `ProgressLemburController` reuses this controller (note `hydrateInputFromBody()` is `protected` for that reason, `ProgressWorkorderController.php:60`). Confirm that lembur start/submit/resubmit paths will automatically inherit the new `tahapan` behavior, or list what extra wiring is needed.
3. Inspect `resubmit()` (`ProgressWorkorderController.php:552`) — it updates a revised progress row in-place. Decide and report whether `tahapan` should be updatable there (expected: yes, optional, same validation).
4. List every consumer of `memberSummary()` (`:1286`) and `quota()` (`:1395`) response keys you can find in this repo (routes file, tests). Treat the Next.js dashboard as an invisible consumer of the **exact current shapes**.
5. Verify the `countableSubmissions()` definition (`app/Support/WorkorderQuota.php`) — it filters `tipe_progress = PROGRESS` only. Decide and report whether `max(tahapan)` should also consider `MULAI`/`SELESAI`/`INSPEKSI` rows (expected: yes — INSPEKSI and MULAI carry tahapan 1, SELESAI carries 4; compute max over all non-cancelled submitted rows of the WO, i.e. `waktu_submit NOT NULL` and not DIBATALKAN).

Deliver a short findings report (file:line cited) + proposed migration and diff plan. Wait for nothing — proceed to Phase 2 in the same run, but the report must come first in your output.

## Phase 2 — Implementation

1. **Migration**: add nullable `tahapan` `unsignedTinyInteger` to `progress_workorder`. No backfill; legacy rows stay `NULL`.
2. **Constants**: new class `App\Constants\TahapanWorkorder` with the four phase constants and a `LABELS` map (generic Indonesian labels above).
3. **`submit()`** (`ProgressWorkorderController.php:331`):
   - Add to `$rules` (`:344-354`): `'tahapan' => 'nullable|integer|between:1,4'`.
   - Persist it in `ProgressWorkorder::create()` (`:449-460`).
   - When `tipe_progress = SELESAI`: **force `tahapan = 4`** server-side regardless of input.
   - When `tipe_progress = INSPEKSI`: **force `tahapan = 1`** server-side regardless of input — inspection is part of the Persiapan phase. Consequence: a WO whose mandatory pre-start inspection has been submitted immediately shows 25% progress, before MULAI.
4. **`start()`** (`:185`): set `tahapan = 1` on the MULAI row (`:270-281`). No new request field needed.
5. **`resubmit()`** (`:552`): accept optional `tahapan` (same rule) and update in-place, per your Phase 1 finding.
6. **`progres_persen` accessor**: new logic —
   - If the WO has ≥1 eligible submission with non-null `tahapan` → `max(tahapan) / 4 × 100` (integer or 2-dp, match current type exactly).
   - Else → **fall back to the existing legacy formula unchanged** (legacy WOs keep sane values).
   - Same key, same numeric type, same 0–100 range. Shape is identical; only semantics change, and that change is intentional.
7. **Additive response fields** (never remove/rename anything):
   - In `start()`/`submit()`/`resubmit()` responses' `workorder` object (`:317-324`, `:517-524`): add `tahapan_tertinggi` (int|null).
   - In `memberSummary()` per-member `statistics` (`:1339-1348`): add `tahapan_tertinggi` (int|null, that member's max) and `progress_tahapan` (0–100|null). All existing keys (`quota_total`, `progress_percentage`, etc.) keep their current values and formulas — they describe the reporting limit and stay for dashboard compatibility.
8. **`quota()` endpoint** (`:1395-1451`): zero functional change. Only update the PHPDoc to state it returns the *daily reporting limit*, not progress.

## Hard Constraints

- [IMPACT: breaks the frozen Next.js dashboard] Do **not** rename, remove, retype, or reorder any existing JSON key or route. This explicitly includes every `*_kuota_*` key in `quota()` (`:1433-1444`) and every key in `memberSummary()` (`:1332-1355`). Additive changes only.
- [IMPACT: breaks already-installed Flutter builds] `tahapan` must be optional everywhere. A request identical to today's traffic must behave byte-for-byte the same except for the new additive response fields.
- [IMPACT: silently reintroduces the rejected metric] `validateProgressLimit()` (`:152-183`) and `App\Support\WorkorderQuota` must remain functionally identical — rate limiting only. Do not "improve" them.
- [IMPACT: data corruption] No UPDATE/backfill on existing `progress_workorder` rows.
- [IMPACT: prod parity] PostgreSQL specifics apply — keep the documented `selectRaw('1')` + GROUP BY pattern (`:155-161`) intact; any new aggregate query must be Postgres-safe.
- Laravel 8-compatible syntax only. Project rules in `AI_CODE_ETHICS_RULES.md` apply in full.
- No new identifier may contain "quota"/"kuota".

## Deliverable Report (standard format)

1. **Summary** — what changed and why, 5 lines max.
2. **Files changed** — each with `file:line` ranges.
3. **API behavior** — sample request/response for: PROGRESS submit with `tahapan`, PROGRESS submit without it (legacy), SELESAI (forced 4), and the new `workorder` / `statistics` fields.
4. **Migration** — exact artisan commands to run.
5. **Test evidence** — manual curl or feature tests proving: legacy submit unaffected; `tahapan` persisted; SELESAI forces 4; INSPEKSI forces 1 (and `progres_persen` shows 25% right after inspection); `progres_persen` tahapan-based when data exists and legacy-fallback when not; `memberSummary` additive fields present; `quota()` response unchanged byte-for-byte.
6. **Risks & follow-ups** — anything deferred.
