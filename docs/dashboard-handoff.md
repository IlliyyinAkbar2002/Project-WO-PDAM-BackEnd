# Dashboard Handoff — Superadmin View

This note documents the backend data sources available for the superadmin dashboard (Next.js). The dashboard *endpoints themselves* are not implemented yet — the file-owner (partner) will pick controller placement and routing. The data model and accessors below are ready to be consumed.

## What was done on the backend

1. **`m_jabatan.kode` column** — added via `2026_05_24_000000_add_kode_to_jabatan_table.php`. Canonical kodes seeded via `JabatanSeeder` at fixed IDs:

   | id | kode | nama |
   |---|---|---|
   | 1 | `MANAGER` | Manager |
   | 2 | `SPV` | Supervisor |
   | 3 | `SENIOR_STAFF` | Senior Staff |
   | 4 | `STAFF` | Staff |

   Lookup pattern mirrors `Status::where('kode', 'IN_PROGRESS')`. ID ordering encodes seniority (lower id = more senior) and is consumed by `PegawaiController::index()`.

2. **SELESAI authorization rule** — in `ProgressWorkorderController::submit()`. Closing a WO with `tipe_progress=SELESAI` now requires the submitter to be (a) a team member with `is_pic=true` AND (b) hold the jabatan with `kode='SENIOR_STAFF'`. Returns 403 with message `"Hanya PIC dengan jabatan Senior Staff yang dapat submit SELESAI"` otherwise.

3. **`Workorder::avg_cadence_minutes` accessor** — auto-appended on every Workorder serialization. Returns the average minutes between consecutive `progress_workorder.waktu_submit` values for the WO (excluding MULAI). `null` if fewer than 2 submissions. Smaller value = faster team.

## Where the dashboard should mount

Recommended (not implemented yet):

```
GET /api/v1/dashboard/team-performance       List + sort by avg_cadence_minutes
GET /api/v1/dashboard/workorder/{id}/breakdown   Per-member progress for one WO
```

Suggested middleware stack: `auth:sanctum` + `role:superadmin`. The `role` middleware is already used elsewhere in `routes/api.php` — grep for `role:superadmin` to see the pattern.

Reference for non-superadmin scoping (if you ever need to show this view to a SPV): see `WorkorderController@index`, which already restricts non-superadmin to WOs they're assigned to.

## Data sources

### Team-performance leaderboard

```php
Workorder::with([
    'workorderAssignment.members.user.pegawai',
    'jenisWorkorder',
    'status',
])
->whereHas('workorderAssignment.members')  // only WOs that have a team
->get()
->sortBy('avg_cadence_minutes');           // accessor handles the math
```

- `avg_cadence_minutes` is an appended attribute — already in the JSON response.
- `progres_persen` (existing accessor) gives 0/quota%/90/100 for the progress bar.
- Tie-breaker is undefined — pick one (e.g., total submission count, or completion %) when you implement.
- WOs with `avg_cadence_minutes = null` (< 2 submissions) should probably sort to the bottom of the leaderboard rather than the top.

### Per-member progress breakdown (for the individual progress card)

```php
$wo = Workorder::with([
    'workorderAssignment.members.user.pegawai',
])->findOrFail($id);

$progressByMember = $wo->progressWorkorder()
    ->with('submitter.pegawai:id,nama,nip')
    ->whereNotNull('waktu_submit')
    ->orderBy('order')
    ->get()
    ->groupBy('submitted_by_user_id');
```

The grouping gives you `{ user_id => [progress_rows...] }`. Each member's submission count, latest submission time, and contribution share can be computed from this.

`submitter` relation is already defined on `ProgressWorkorder` and eager-loads `pegawai:id,nama,nip`.

## Testing setup

The seeded data is wired so the SELESAI guard works out of the box: pegawai `David` (login `david123@gmail.com`) is seeded with `jabatan_id = 3` (`SENIOR_STAFF`), so once a SPV assigns David to a WO with `peran=koordinator` (which sets `is_pic=true`), David can submit `tipe_progress=SELESAI`.

To exercise the rejection paths:
- Non-PIC member submits SELESAI → 403
- PIC member whose pegawai jabatan is `STAFF` (e.g. Budi at `jabatan_id = 4`) submits SELESAI → 403

## Open questions for the dashboard owner

- **Timeframe filter** — should the leaderboard be all-time or rolling window (last 30 days)?
- **Cadence tie-breaker** — when two teams have equal `avg_cadence_minutes`, what comes next? Total submissions? Completion %?
- **Inactive teams** — should WOs with `null` cadence (single or zero submissions) be hidden, shown grayed-out, or sorted to the bottom?
- **Aggregation by team** — current accessor is per-WO. If a team works multiple WOs, the dashboard probably wants a team-level rollup. Decide whether to compute on the client or add a service method.
- **Caching** — `avg_cadence_minutes` runs a query per WO. For a large leaderboard, prefer a single aggregate SQL or cache the value (`updated_at`-keyed) rather than relying on the accessor.
