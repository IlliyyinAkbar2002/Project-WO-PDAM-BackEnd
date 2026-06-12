# Backend Task: PIC-Only Tahapan Advancement (Milestone Write Gate)

## Context

The work order progress system uses milestone-based tracking: `tahapan` (int 1–4: Persiapan → Pengerjaan → Pengujian → Dokumentasi) on `progress_workorder` rows. The WO-level milestone is derived as `MAX(tahapan)` over submitted, non-cancelled rows, and `progres_persen` is computed from it (tahapan-based with legacy fallback).

**New business rule:** the milestone is a property of the *work order*, and only the team coordinator (PIC — the assignment member with `is_pic = true`) may **advance** it. Regular members may still tag their PROGRESS reports with a `tahapan` value **up to and including** the WO's current tahapan (e.g. late documentation for an already-reached stage), but may never push it forward.

This task is **write-side validation only**. No response shape, route, query, or computed-progress change.

## Phase 1 — Analyze First (no code before citations)

Investigate `app/Http/Controllers/ProgressWorkorderController.php` and report with `file:line` citations:

1. The `tahapan` validation rule in `submit()` (≈ line 294) and `resubmit()` (≈ line 506).
2. The existing SELESAI PIC gate in `submit()` (≈ lines 363–377) and `resubmit()` (≈ lines 549–563) — this is the established pattern for detecting `is_pic` from `assignmentMembers`; the new gate must reuse the same detection approach.
3. The tahapan assignment blocks (`submit()` ≈ 383–388, `resubmit()` ≈ 566–571) confirming: SELESAI is forced to `TahapanWorkorder::DOKUMENTASI`, INSPEKSI is forced to `TahapanWorkorder::PERSIAPAN`, and only tipe **PROGRESS** carries a user-supplied tahapan. Also confirm `start()` (MULAI) hard-sets `TahapanWorkorder::PERSIAPAN` (≈ line 216).
4. The existing WO-level `tahapan_tertinggi` query (present in `start()` ≈ 251–254, `submit()` ≈ 459–462, `resubmit()` ≈ 641–644: `whereNotNull('waktu_submit')` + exclude DIBATALKAN + `max('tahapan')`). The validation threshold MUST be this exact query, so the limit a member sees matches the number the API already reports.
5. Where `is_pic` lives in the data model (`workorder_assignment` members relation) — confirm field name and relation path.

Deliver the Phase 1 findings before writing any code.

## Phase 2 — Implement

1. In `submit()` and `resubmit()`, **for tipe PROGRESS only**, after the membership check and before the DB transaction:
   - Compute `$currentTahapan` using the exact existing `tahapan_tertinggi` query for this workorder; coalesce `null → 1`.
   - Determine whether the requester is the PIC for this WO (same `assignmentMembers` / `is_pic` pattern as the SELESAI gate).
   - If the requester is **not** PIC and the request's `tahapan` is non-null and `> $currentTahapan`, return `422` with JSON: `{"error": "Hanya koordinator (PIC) yang dapat memajukan tahapan pekerjaan"}`.
   - PIC requests are unchanged (any value 1–4 allowed).
   - Null/omitted `tahapan` is unchanged and always allowed for everyone.
2. Small private helpers are welcome (e.g. `isPicForWorkorder(Workorder $wo, ?int $userId): bool`, `currentTahapan(int $workorderId): int`) to avoid duplicating logic across `submit()`/`resubmit()`. Helper names must not contain "quota"/"kuota".
3. In `resubmit()`, the threshold query includes ALL rows — do **not** exclude the row being edited. (A member re-saving their own row at its existing tahapan must remain valid; since their row is part of the max, inclusion makes this safe automatically.)
4. Feature tests (extend the existing progress submission test files) covering:
   - Member submits PROGRESS with `tahapan` > current → 422 with the exact message.
   - Member submits PROGRESS with `tahapan` == current and < current → 201.
   - Member submits PROGRESS with no `tahapan` → 201, stored `tahapan` is null.
   - PIC submits PROGRESS with `tahapan` = current + 1 → 201, WO `tahapan_tertinggi` advances.
   - SELESAI behavior unaffected (still forced 4, still PIC + SENIOR_STAFF gated).
   - Same matrix for `resubmit()`, including member resubmitting their own row at unchanged tahapan → 201.
   - Fresh WO with zero tahapan rows: member may submit `tahapan = 1` (threshold coalesces to 1) → 201.

## Hard Constraints

- [IMPACT: breaks the frozen Next.js dashboard] Do not rename, remove, retype, reorder, or add any JSON key or route. This task changes **zero** response shapes — validation only.
- [IMPACT: breaks already-installed Flutter builds] Requests identical to today's traffic must behave byte-for-byte the same, with one deliberate exception: a non-PIC member sending a tahapan above the current WO tahapan now receives 422. Everything else — including all null-tahapan requests — is unchanged.
- [IMPACT: silently corrupts legacy progress semantics] NEVER default a null/omitted `tahapan` to the current WO tahapan (or to any value) server-side. A legacy WO with no tahapan rows must keep computing `progres_persen` via the legacy fallback; server-side defaulting would silently flip it to tahapan-based at 25% on the first legacy-build submission. Null stays null. Pre-selection is a Flutter concern only.
- [IMPACT: data corruption] No UPDATE/backfill of existing `progress_workorder` rows. Historical rows where a member's tahapan exceeds the PIC's are valid history and must be left alone.
- The new gate keys off `is_pic` **only**. Do NOT add a jabatan requirement — `SENIOR_STAFF` belongs exclusively to the existing SELESAI gate, which must not be modified.
- Do not touch: `review()`, `cancel()`, `memberSummary()`, `progressByMember()`, the `tahapan_tertinggi` response queries, or the `progres_persen` computation in the `Workorder` model.
- [IMPACT: prod parity] PostgreSQL-safe queries only. Laravel 8-compatible syntax only. Project rules in `AI_CODE_ETHICS_RULES.md` apply in full.
- No new identifier may contain "quota"/"kuota".

## Deliverable Report (standard format)

1. **Summary** — what changed and why, 5 lines max.
2. **Files changed** — each with `file:line` ranges.
3. **API behavior** — sample request/response for: member blocked (422), member equal-stage tag (201), member null-tahapan (201), PIC advancement (201), SELESAI unchanged.
4. **Migration** — none expected; state this explicitly and confirm no schema change.
5. **Test evidence** — feature test output proving the matrix in Phase 2 item 4.
6. **Risks & follow-ups** — anything deferred (e.g. legacy rows above PIC max remaining visible in stats).