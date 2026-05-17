# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

Backend API for a PDAM (water utility company) work order management system. Built with Laravel 8 + Sanctum, backed by PostgreSQL. Serves two clients: a Flutter mobile app (field workers) and a Next.js web dashboard (admin/SPV).

## Commands

```bash
# Serve locally
php artisan serve

# Run all tests
php artisan test
# or
./vendor/bin/phpunit

# Run a single test file
php artisan test --filter=WorkorderCreationTest

# Run migrations
php artisan migrate

# Seed database (idempotent for master data)
php artisan db:seed

# Clear caches after config/route changes
php artisan config:clear && php artisan route:clear && php artisan cache:clear

# List all routes
php artisan route:list --path=api/v1
```

## Architecture

### Domain Model

The core workflow is a multi-stage work order lifecycle:

1. **Superadmin** creates a WO and assigns it to a **SPV** (status: `DITUGASKAN_KE_SPV`)
2. **SPV** fills a category-specific form (meter/jaringan/infrastruktur) and assigns **Staff** members (status: `DITUGASKAN_KE_STAFF`)
3. **Staff** reports progress with geolocation + photos (status: `IN_PROGRESS`)
4. **Staff** marks work complete (status: `PENGECEKAN`)
5. **SPV** reviews: accept → `SELESAI`, revisi → back to `IN_PROGRESS`, tolak → `DITOLAK_SPV`

Progress reporting has a quota system: max 8 submissions per day, total quota = estimated_days * 8.

### Service Layer

Business logic lives in `app/Services/`, not controllers:

- `WorkorderService` — WO creation (Superadmin domain)
- `AssignmentService` — SPV assigns staff, creates category form, geofencing location
- `ProgressWorkorderService` — progress lifecycle, status transitions
- `WorkorderActionService` — audit trail (workorder_action table)

### Key Models & Relationships

- `Workorder` → has one `WorkorderAssignment` (SPV assignment with timeline/location)
- `WorkorderAssignment` → has many `WoAssignmentMember` (staff team)
- `Workorder` → has one of: `WoMeter`, `WoJaringan`, `WoInfrastruktur` (category form, determined by `jenis_workorder.kategori_form`)
- `Workorder` → has many `ProgressWorkorder` (ordered progress entries with geolocation)
- `ProgressWorkorder` → has many `DokumentasiProgress` (photos)

### Status & Master Data

Statuses are looked up by `kode` column (e.g., `Status::where('kode', 'IN_PROGRESS')`), not by hardcoded IDs. The `StatusSeeder` defines all 18 canonical statuses. `TipeProgress` codes: MULAI, PROGRESS, SELESAI, REVISI, DITOLAK.

### Authentication & Authorization

- Sanctum token-based auth. Token has `access` ability.
- Roles: `superadmin` (role_id=1), `manager` (role_id=2), `employee` (role_id=3, covers both SPV and Staff — distinguished by `jabatan`)
- Role middleware: `->middleware('role:superadmin')`
- Non-superadmin users only see WOs they're assigned to (as PIC or team member)

### API Structure

All routes under `/api/v1/`. Key patterns:
- Standard CRUD via `apiResource`
- Progress endpoints use `match(['post','put','patch'])` for client compatibility
- `ProgressWorkorderController` has a `hydrateInputFromBody` helper for multipart/json compat with older mobile clients

### Database

- PostgreSQL (uses `ILIKE`, `::date` casts, `setval` for sequences)
- Table names use Indonesian: `workorder`, `m_status`, `m_pegawai`, `m_departemen`, `m_jenis_workorder`, `m_location`, `progress_workorder`, `workorder_assignment`, `wo_assignment_member`
- Master/reference tables prefixed with `m_`

### File Storage

Progress photos stored via Laravel filesystem (`storage/app/public/dokumentasi_progress`). Uploaded as multipart with max 2048KB per image.
