# [ANALYSIS ONLY] Impact Assessment: Full Removal of the "Quota" Concept from the Backend

## Mission

Produce a **read-only impact analysis** for completely deleting the quota concept from this Laravel 8 backend. **Do not modify, create, or delete any file in this task.** Your output is a report, not code. A separate execution prompt will follow after the human reviews your findings.

## Context (why)

- The thesis supervisor has rejected the quota concept (daily/total submission limits as a progress proxy). Enforcement was already removed in a previous task (`validateProgressLimit()` and its call sites are gone; the old 422 rejections no longer exist).
- In that task, the `quota()` endpoint was kept alive but marked `@deprecated`, and the `App\Support\WorkorderQuota` class was kept because it backs the legacy fallback of `progres_persen`.
- The developer now wants to evaluate **deleting all of it** so no quota-related dead code remains visible in the source during the thesis defense.
- Progress is now milestone-based (`tahapan` 1–4). That system must remain fully intact.

## Scope of the proposed deletion (candidates to analyze — NOT to delete yet)

1. `ProgressWorkorderController::quota()` and its route registration.
2. The entire `App\Support\WorkorderQuota` class (`totalDays()`, `quotaTotal()`, `countableSubmissions()`, `SUBMISSIONS_PER_DAY`).
3. Quota-derived response fields in other endpoints, currently known:
   - `progressByMember()` → `quota_remaining`, `quota_total` (per member).
   - `memberSummary()` → `statistics.quota_total`, `statistics.quota_remaining`, and `statistics.progress_percentage` (quota-based), `team_statistics.avg_progress_percentage`.
4. The legacy quota-based fallback inside the `progres_persen` computation (likely in `app/Models/Workorder.php` — locate the exact accessor/method).
5. All remaining quota/kuota identifiers, comments, and strings in the backend.
6. All tests that call the quota endpoint or assert on quota fields.

## Phase 1 — Required analysis (cite every finding as `file:line`)

1. **Full inventory.** Run a case-insensitive grep for `quota` and `kuota` across the entire backend (`app/`, `routes/`, `tests/`, `database/`, `config/`). List every hit with `file:line` and classify it: executable code, response field, comment/docblock, test assertion, migration/seed, or string.

2. **Route map.** Cite where the quota endpoint is registered in `routes/api.php` (method, URI, middleware). Confirm whether any other route or controller calls `quota()` internally.

3. **Hidden non-quota payloads.** `quota()` currently returns `bisa_cancel` (IDs of submissions cancelable within the 5-minute window, status `SUBMITTED`). Determine with certainty:
   - whether `bisa_cancel` is computed **anywhere else** in the backend, or only inside `quota()`;
   - whether any backend test (especially the cancel feature tests) obtains cancelable IDs via this endpoint.
   If `quota()` is the **only** server-side source of `bisa_cancel`, flag this as `[NEEDS RELOCATION]` — deleting the endpoint silently amputates the cancel-window discovery mechanism, which is unrelated to quota.

4. **`progres_persen` fallback autopsy.** Locate the exact code path where `progres_persen` falls back to quota-based math when no tahapan data exists. Then answer, with evidence:
   - What value would `progres_persen` return for a work order with **zero** tahapan-bearing submissions if the fallback is deleted (0? null? exception?)?
   - Query the dev database (read-only) for how many existing work orders would be affected: count of WOs whose submissions have no non-null `tahapan` at all, grouped by status.
   - Propose (in the report only) the simplest tahapan-pure replacement for the fallback, e.g. status-derived: no MULAI → 0, otherwise `max(tahapan)/4 × 100`.

5. **Dashboard-facing fields.** `memberSummary()` is documented as the Web Dashboard (Next.js) endpoint. The backend repo cannot see the Next.js code, so do NOT guess. Instead, enumerate exactly which quota-derived fields appear in `memberSummary()` and `progressByMember()` responses, and mark each as `[BLOCKED — requires external verification]`. The human will verify Next.js consumption separately.

6. **Test impact.** List every test file/method that would fail after full deletion, with `file:line` and the reason (404 on deleted route, missing response key, missing class). Distinguish tests that should be deleted (they test the quota concept itself) from tests that must be rewritten (they test something else but use quota fields as a vehicle, e.g. cancel tests).

7. **Collateral grep.** Check for any scheduled jobs, console commands, observers, resources/transformers, or policies referencing `WorkorderQuota` or quota fields beyond the controller and model.

## Classification scheme (use exactly these tags in the report)

- `[SAFE TO DELETE]` — no remaining consumer inside the backend; removal is mechanical.
- `[NEEDS RELOCATION]` — the code carries a non-quota responsibility that must be moved first (e.g. `bisa_cancel`).
- `[NEEDS REPLACEMENT]` — deletion changes observable behavior and a tahapan-based substitute must land in the same commit (e.g. `progres_persen` fallback, `memberSummary.progress_percentage`).
- `[BLOCKED — requires external verification]` — consumed (or possibly consumed) by Next.js or installed Flutter builds; the human decides.

## Hard constraints

- [IMPACT: corrupts the analysis] **Read-only task.** No file edits, no migrations, no test runs that mutate data. Database access only via SELECT.
- [IMPACT: breaks the frozen web dashboard] Do not propose renaming or reshaping any existing response field consumed by Next.js as part of this analysis; deletion candidates touching dashboard endpoints must be tagged `[BLOCKED]`, not recommended outright.
- [IMPACT: invalidates the thesis narrative] The tahapan system (forcing SELESAI→4, MULAI→1, `tahapan`-based `progres_persen`) is out of scope and must not appear in the deletion plan except where it replaces quota fallbacks.
- `AI_CODE_ETHICS_RULES.md` applies. Laravel 8 / PostgreSQL context.

## Deliverable Report (standard format)

1. **Summary** — 5 lines max: is full deletion feasible, and what are the blockers.
2. **Inventory table** — every quota/kuota occurrence: `file:line` | kind | classification tag | reason.
3. **Dependency map** — short text diagram showing what consumes `WorkorderQuota` and `quota()` (controller methods, model accessor, tests, routes).
4. **`bisa_cancel` verdict** — sole-source or not, with evidence.
5. **`progres_persen` fallback verdict** — affected-WO counts from the dev DB and the proposed replacement.
6. **Test impact list** — delete vs rewrite, with reasons.
7. **Recommended deletion order** — a numbered sequence the future execution prompt should follow, including which steps must be atomic (same commit).
8. **Open questions for the human** — at minimum: (a) does Next.js read `quota_total`/`quota_remaining`/`progress_percentage` from `memberSummary` or `progressByMember`; (b) does the Flutter cancel feature read `bisa_cancel` from the quota endpoint; (c) are 404s acceptable for older installed Flutter builds that still call the endpoint.