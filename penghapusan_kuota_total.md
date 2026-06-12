# [EXECUTION] Total Removal of the "Quota" Concept from the Backend

## Mission

Remove the quota concept from this Laravel 8 backend **completely**: no class, method, route, response field, constant, comment, or test may contain the words `quota`/`kuota` (any casing) when you are done. This executes the plan validated by the prior analysis report (`Analisa_dampak_kuota_dihapus.md`). The tahapan milestone system is the sole progress mechanism and must remain untouched except where it replaces quota fallbacks.

## Resolved decisions (binding — do not re-litigate)

- **D1 — `bisa_cancel` relocation.** Create a new lightweight endpoint per controller, mirroring the existing route structure of the old quota routes (`routes/api.php:66, 100`):
  - `GET /api/v1/workorder/{workorderId}/progress/cancelable` → `ProgressWorkorderController@cancelable`
  - the lembur equivalent → `ProgressLemburController@cancelable`
  Response shape: `{ "workorder_id": ..., "user_id": ..., "bisa_cancel": [ids] }`. The `bisa_cancel` key name, the ID semantics, and the computation (owned by current user, status `SUBMITTED`, `waktu_submit` non-null and within the last 5 minutes) must be byte-for-byte equivalent to the old logic at `ProgressWorkorderController.php:1376-1381` and `ProgressLemburController` (~line 61). Same auth middleware as the old quota routes.
- **D2 — `progres_persen` fallback replacement.** In `Workorder.php:176-192` (`getProgresPersenAttribute`), tahapan remains the primary source. Replace the quota-based legacy fallback with pure status-derived values, exactly as proposed in the analysis: `SELESAI → 100`, `PENGECEKAN → 90`, otherwise `0`. Dev DB shows 0 affected work orders, so no observable regression is expected — still prove it (see deliverables).
- **D3 — Dashboard endpoints (`memberSummary`, `progressByMember`).** ⛔ **GATED STEP** — see Gate below.
  - Remove `quota_total` and `quota_remaining` from both responses.
  - Keep the key `statistics.progress_percentage` but change its source: it becomes the tahapan-based value (identical to `progress_tahapan`, defaulting to `0` when null). `team_statistics.avg_progress_percentage` follows automatically. Keep `progress_tahapan` and `tahapan_tertinggi` as-is.
  - Keep `total_submissions`, `today_submissions`, `avg_submissions_per_day`, `days_active`, `daily_breakdown`, `first_submission`, `last_submission`, and `estimasi_hari` — these are quota-free statistics and the frozen Next.js dashboard may read them.
- **D4 — Endpoint deletion.** Delete `ProgressWorkorderController@quota` (lines ~1334-1401), `ProgressLemburController@quota`, and both route registrations. Older installed Flutter builds receiving 404 on these routes is **accepted and expected** (same policy as the limit-removal task). Do not add redirects, stubs, or tombstone responses.
- **D5 — Helper relocation (no quota names).** `WorkorderQuota` dies, but two pieces of its logic survive under quota-free names:
  - `countableSubmissions()` → move to a query scope on the `ProgressWorkorder` model, e.g. `scopeSubmittedProgress($query, int $workorderId, int $userId)` (PROGRESS type, non-null `waktu_submit`, by user). Refactor `progressByMember`/`memberSummary` call sites to use it.
  - `totalDays()` → this is schedule math, not quota math. Relocate it (e.g. `App\Support\WorkorderSchedule::totalDays()` or a Workorder model method) and keep feeding the `estimasi_hari` response key from it. Same `max(1, diffInDays)` semantics — do not reintroduce the old off-by-one.
  - `quotaTotal()` and `SUBMISSIONS_PER_DAY` are deleted with no successor.
- **D6 — Final purge.** Delete `app/Support/WorkorderQuota.php` and `tests/Unit/WorkorderQuotaTest.php`. Remove the leftover comments: `ProgressWorkorderController.php:247, 457` ("termasuk cek kuota"), `:1160, :1179, :1232` ("untuk kuota", "Statistik kuota"), and the quota comments in `tests/Feature/ProgressSubmissionTest.php:21, 84`. Update the `quota()` docblocks by deleting them along with the methods.

## ⛔ Gate before D3/D4

Before executing D3 and D4, the human must have stated in this conversation the result of grepping the **Next.js dashboard repo** for `quota_total`, `quota_remaining`, and `progress_percentage`:

- If the human says **"NEXTJS CLEAR"** (no hits, or hits accepted) → proceed with D3 and D4 as written.
- If the human reports the dashboard **does** read `quota_total`/`quota_remaining` → STOP after Atomic 1, report, and wait for instructions. Do not improvise a compatibility shim.
- If the human has said **nothing** about Next.js → STOP after Atomic 1 and ask.

## Execution order (commit per atomic block)

**Step 0 — Clean-tree check.** Run `git status` and `git diff`. The prior analysis session was supposed to be read-only but its tool log shows edits to `ProgressWorkorderController.php` and a `scratch.php`. If the working tree is not clean, report every uncommitted change verbatim and wait for the human's revert/keep decision before touching anything.

**Phase 1 — Re-cite (compact).** Line numbers may have drifted. Before editing, re-locate and cite with current `file:line`: both `quota()` methods, both route registrations, the `progres_persen` accessor, every `WorkorderQuota` call site, and every `quota|kuota` comment. No code until this list is posted.

**Atomic 1 (commit 1):** D2 (`progres_persen` pure fallback) + D1 (`cancelable` endpoints) + D5 (scope + schedule relocation, refactor call sites off `WorkorderQuota` everywhere except the two `quota()` methods themselves). Add feature tests for the new `cancelable` endpoints (returns the same IDs the old endpoint would; empty after 5 minutes; only own submissions).

**Atomic 2 (commit 2, gated):** D3 (strip quota fields, re-source `progress_percentage`) + D4 (delete both `quota()` methods and routes).

**Atomic 3 (commit 3):** D6 (delete `WorkorderQuota` + its unit test + all comments) and the final grep gate.

## Hard constraints

- [IMPACT: breaks the Flutter cancel feature] The `bisa_cancel` key name and ID list semantics in the new `cancelable` endpoints must match the old output exactly. A separate FE prompt will swap the URL on the Flutter side; nothing else may change for it.
- [IMPACT: breaks the frozen Next.js dashboard] Besides removing `quota_total`/`quota_remaining` (gated), no existing response key on any endpoint may be renamed, removed, reshaped, or re-typed. `progress_percentage` and `estimasi_hari` must remain present and numeric.
- [IMPACT: invalidates the thesis narrative] Tahapan logic (MULAI→1, SELESAI→4 forcing, tahapan-based `progres_persen`) must not be modified.
- [IMPACT: data corruption] Code-only task. No new migrations, no schema changes, no edits to existing migrations. If grep finds `quota`/`kuota` inside an already-run migration or seeder, report it — do not edit it.
- The earlier behavior must hold: a 9th/15th/30th same-day `PROGRESS` submission still returns 201 and persists. Nothing in this task may reintroduce any limit.
- Laravel 8-compatible syntax only; every query Postgres-safe; `AI_CODE_ETHICS_RULES.md` applies.

## Final verification gate (Atomic 3, mandatory)

Run `grep -rin "quota\|kuota" app/ routes/ tests/ config/ database/` — the required result is **zero hits** (or, if hits exist only in already-run migrations/seeders, list them as accepted exceptions in the report). Paste the raw grep output.

## Deliverable Report (standard format)

1. **Summary** — what changed, 5 lines max.
2. **Files changed** — each with `file:line` ranges, grouped by atomic commit.
3. **Behavior proof** — curl or feature-test evidence that: (a) the new `cancelable` endpoint returns the same IDs the old `quota()` would for a fresh `SUBMITTED` progress, and an empty list after the 5-minute window; (b) `progres_persen` is unchanged for a tahapan-bearing WO, and a no-tahapan WO with status SELESAI/PENGECEKAN returns 100/90; (c) `memberSummary` response diff shows only `quota_total`/`quota_remaining` removed and `progress_percentage` now equal to the tahapan value; (d) old quota routes return 404; (e) a 9th same-day PROGRESS submit returns 201.
4. **Grep proof** — raw output of the final verification gate showing zero hits.
5. **Test suite results** — full run output, listing deleted vs rewritten tests and why.
6. **Risks & follow-ups** — at minimum: the pending Flutter URL swap for `bisa_cancel`, and confirmation of what the human decided at the Next.js gate.