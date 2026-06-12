# Backend Task: Remove Daily Reporting Limit Enforcement (8×/day)

## Context

The milestone-based progress feature ("tahapan") is now live: progress is computed from `max(tahapan)/4`, fully decoupled from submission counts. The 8-submissions-per-day quota originally existed only as the progress denominator — that role is gone. Product decision (final, approved by the academic supervisor): **remove the daily submission limit entirely. No replacement** — no cooldown, no soft cap. Field staff report as often as the work requires.

Compatibility rule stays the same as previous tasks: the `quota()` endpoint and all existing JSON keys remain as **display-only legacy** for installed clients and the frozen Next.js dashboard. Only the *enforcement* dies.

## Phase 1 — Analysis (report findings BEFORE writing any code)

1. Locate **every** call site of `validateProgressLimit()` (`ProgressWorkorderController.php:152-183`). Expected: `submit()` for `PROGRESS` type (around `:439-443`). Also check `start()`, `resubmit()`, and `ProgressLemburController` for direct or inherited usage.
2. Inspect `Feature/ProgressCancelTest.php` and the rest of the test suite for assertions that depend on the 8/day enforcement (e.g., expecting HTTP 422 on the 9th same-day submission). List each with `file:line`.
3. Confirm the remaining consumers of `App\Support\WorkorderQuota` after removal — expected: `quota()` endpoint, `memberSummary()`, and the legacy fallback branch inside `getProgresPersenAttribute()` (`app/Models/Workorder.php:165-195`). The class and its `SUBMISSIONS_PER_DAY` constant must therefore **stay**.
4. Output the findings report first, then proceed to Phase 2 in the same run.

## Phase 2 — Implementation

1. Remove the `validateProgressLimit()` call from `submit()` (and any other call site found in Phase 1). Delete the now-unused private method.
2. Update the `quota()` PHPDoc (`:1395`): mark it `@deprecated` — display-only legacy endpoint retained for mobile compatibility; the server no longer enforces any submission limit. **Do not change its logic, keys, values, or route.**
3. Update or remove only the test assertions that asserted the quota rejection; the rest of each test must keep passing. Run the suite and report results.
4. Nothing else changes.

## Hard Constraints

- [IMPACT: breaks the frozen Next.js dashboard] No JSON key, value semantics, or route changes anywhere — `quota()` and `memberSummary()` responses stay byte-identical.
- [IMPACT: regression of the just-shipped milestone feature] Do not touch any tahapan code path: the migration, `App\Constants\TahapanWorkorder`, the reordered `getProgresPersenAttribute()`, the forced `SELESAI=4` / `INSPEKSI=1` rules, or the `tahapan_tertinggi` response fields.
- [IMPACT: breaks installed Flutter builds] Older app builds may still call `quota()` and self-disable their submit button at 8 — that is acceptable and expected. Do not "compensate" by altering the endpoint's returned numbers.
- [IMPACT: silently keeps the rejected behavior] After this task, a user must be able to submit a 9th, 15th, or 30th `PROGRESS` report on the same day and receive HTTP 201/200 with the row persisted. No code path may return the old "Limit pelaporan harian" or "Sisa kuota pelaporan Anda habis" errors — grep-verify both strings are gone.
- Laravel 8-compatible syntax only; `AI_CODE_ETHICS_RULES.md` applies; any query you touch must remain Postgres-safe.

## Deliverable Report (standard format)

1. **Summary** — what changed, 5 lines max.
2. **Files changed** — each with `file:line` ranges.
3. **Behavior proof** — curl or feature-test evidence that: the 9th same-day PROGRESS submit succeeds and persists; tahapan forcing still works (SELESAI→4, INSPEKSI→1); `progres_persen` unaffected; `quota()` response unchanged byte-for-byte.
4. **Test suite results** — full run output, with the list of assertions you updated/removed and why.
5. **Risks & follow-ups** — anything deferred (e.g., eventual retirement of `quota()` post-defense).
