# AI Code Writing Ethics & Safety Rules
> Capstone Project — Multi-Frontend Architecture (Flutter + Next.js) with Laravel 8 Backend
 
---
 
## 1. General Code Generation Principles
 
- **Understand before writing.** Before generating any code, fully analyze the existing codebase structure, dependencies, and the feature being requested. Never generate code based on assumptions alone.
- **Minimal footprint.** Generate only the code that is strictly necessary to fulfill the requested task. Avoid over-engineering or adding unrequested features.
- **Explicit over implicit.** All logic must be clearly expressed. Avoid "magic" patterns that obscure what the code is doing.
- **Consistency.** Follow the existing code style, naming conventions, folder structure, and design patterns already present in the project.
- **No silent changes.** Every modification must be explicitly acknowledged. If a change has side effects, they must be declared before the code is written.
---
 
## 2. Architecture Overview & Safety Protocol
 
This project uses a **Laravel 8 backend** that serves as the single source of truth for **two separate frontends**:
 
| Layer   | Technology | Role                  | Risk Level             |
|---------|------------|-----------------------|------------------------|
| Backend | Laravel 8  | REST API / Core Logic | **HIGH — Shared Core** |
| Mobile  | Flutter    | Frontend (Active Dev) | Medium                 |
| Web     | Next.js    | Frontend              | **HIGH — Protected**   |
 
> 👨‍💻 **Developer Context:** The active developer is working on the **Flutter (mobile)** side. Any task involving the Laravel 8 backend must be treated with extra caution, as backend changes can silently break the **Next.js web frontend**.
 
### 2.1 Next.js Frontend — Protected Zone
 
> ⚠️ **The Next.js frontend is the primary protected surface. Any change to shared resources (API contracts, data structures, environment variables, routing) must be evaluated for its impact on Next.js before being applied.**
 
- **Never modify API response structures** without explicit confirmation that the Next.js frontend has been updated or is unaffected.
- **Never rename, remove, or reorder fields** in any API response consumed by the web frontend.
- **Never change HTTP methods, route paths, or status codes** of existing endpoints without a full impact assessment.
- **Never alter shared environment variables** (`.env`) in a way that breaks the Next.js build or runtime.
- If a change is isolated to Flutter only, explicitly mark it as `[MOBILE ONLY]` in comments or commit messages.
### 2.2 API Contract Rules
 
- Treat every API endpoint as a **public contract**. Changes are **breaking by default** until proven otherwise.
- Prefer **additive changes** (new fields, new endpoints) over **modifying existing ones**.
- If a breaking change is unavoidable, version the endpoint (e.g., `/api/v2/resource`) rather than altering the existing route.
- Document any new or changed endpoint immediately in the API specification file.
---
 
## 2a. Laravel 8 Backend — Special Rules
 
> ⚠️ **Laravel 8 is the backbone of both frontends. Careless backend changes are the most likely source of Next.js breakage, even when the intent is only to fix a Flutter issue.**
 
### 2a.1 Eloquent Models & API Resources
 
- **Never remove or rename a model attribute** that is exposed through an `API Resource` class (`app/Http/Resources/`). This directly impacts the JSON consumed by Next.js.
- **Never modify the `toArray()` method** of any existing API Resource without a full impact check on both frontends.
- When adding new attributes to a model, check if the model uses `$hidden` or `$visible` — do not accidentally expose sensitive fields.
- **Never change the data type of an existing field** (e.g., from `integer` to `string`) in a response — this is a silent breaking change.
### 2a.2 Routes (`routes/api.php`)
 
- **Never change an existing route URI, HTTP verb, or middleware** without verifying Next.js is unaffected.
- New routes for Flutter features must be clearly commented with `// [MOBILE ONLY]`.
- Do not remove or disable any existing route, even if it appears unused — it may be consumed by Next.js.
- Route parameter changes (e.g., `{id}` to `{slug}`) are **breaking changes** and must be treated as such.
### 2a.3 Controllers
 
- When adding a new method to an existing controller, do not alter the logic of existing methods in the same file.
- **Never change the response structure** (`return response()->json(...)`) of an existing controller method without explicit approval.
- If fixing a bug in a controller used by Flutter, verify the same endpoint is not shared with Next.js before applying the fix.
### 2a.4 Middleware & Authentication
 
- **Never modify existing middleware** (`app/Http/Middleware/`) without a full impact assessment across both frontends.
- Laravel Sanctum / Passport token behavior must not be changed, as it affects authentication on all clients.
- Do not add or remove middleware from the `$routeMiddleware` array in `app/Http/Kernel.php` without approval.
### 2a.5 Database (Migrations & Eloquent)
 
- **Never modify an existing migration file.** Always create a new migration to alter an existing table.
- Column renames or removals are **always destructive** — require explicit warning and rollback plan.
- Changing a column's data type or nullability can silently break API responses consumed by Next.js.
- When adding a new column, set a **safe default value** to avoid breaking existing records.
### 2a.6 Laravel Version Constraints (v8 Specific)
 
- This project runs **Laravel 8**. Do not suggest syntax, features, or packages that require Laravel 9 or later (e.g., `enum` casting introduced in L9, new `str()` helpers, etc.).
- Use `Str::` and `Arr::` facades, not newer fluent syntax from L9+.
- Route model binding and implicit binding behavior follows L8 conventions — do not assume L9+ behavior.
- Stick to `php artisan` commands available in L8. Verify compatibility before suggesting any artisan command.
---
 
## 3. Before Writing Any Code — Checklist
 
Before generating or suggesting code, the AI must internally verify:
 
- [ ] Is this change isolated to Flutter (mobile) only, with no backend involvement?
- [ ] If the backend (Laravel 8) is involved, does it modify a shared API endpoint, Resource, or Model?
- [ ] Could this Laravel change break the Next.js build, SSR, or client-side data fetching?
- [ ] Is the suggested syntax/feature compatible with **Laravel 8** specifically?
- [ ] Are there environment variables involved that are shared across frontends?
- [ ] Does this introduce new Composer packages that may conflict with existing Laravel 8 dependencies?
- [ ] Is there an existing pattern in the codebase that should be followed instead?
If any of the above is **yes**, a warning must be raised before the code is presented.
 
---
 
## 4. Code Modification Rules
 
- **Surgical edits only.** When fixing a bug or adding a feature, change only the lines necessary. Do not refactor surrounding code unless explicitly asked.
- **Preserve existing interfaces.** Function signatures, class constructors, and exported types must not change unless the change is the explicit goal of the task.
- **No cascading refactors.** Do not rename variables, reorganize imports, or restructure files as a side effect of an unrelated task.
- **Highlight all diffs.** When modifying existing code, always clearly indicate what was changed, added, or removed.
---
 
## 5. Dependency Management
 
- **Do not add new dependencies without justification.** Every new package must have a stated reason for its inclusion.
- **Check for existing solutions first.** Before introducing a new library, verify the existing dependencies cannot solve the problem.
- **Version-pin new dependencies.** Always suggest pinning to a specific version to prevent unexpected breaking updates.
- **Separate concerns by platform.** Flutter-specific packages must never be added to the Next.js project and vice versa.
- **Laravel/Composer packages must be Laravel 8 compatible.** Always verify a package supports Laravel 8 before suggesting it — do not assume latest version compatibility.
- **Never suggest upgrading Laravel itself.** The project is pinned to Laravel 8. Upgrade suggestions are out of scope.
---
 
## 6. Database & Migration Safety
 
- **Never auto-generate destructive migrations.** Any migration involving `DROP`, `TRUNCATE`, `DELETE`, or column removal must include an explicit warning and require manual confirmation.
- **Always include rollback steps.** Every migration suggestion must be paired with a rollback plan.
- **Seed data is not production data.** Never write code that confuses seeder logic with live data operations.
---
 
## 7. Environment & Configuration Rules
 
- **Never hardcode secrets.** API keys, tokens, passwords, and credentials must always reference environment variables.
- **Never commit `.env` files.** Remind the developer to add `.env` to `.gitignore` if it is not already there.
- **Document every new env variable.** Any new environment variable introduced must be documented in `.env.example` with a placeholder value and a comment explaining its purpose.
---
 
## 8. Security Baseline
 
- **Validate all inputs.** Never generate code that accepts user input without validation or sanitization.
- **Never expose sensitive data in API responses.** Fields like passwords, tokens, or internal IDs must be explicitly excluded from response payloads.
- **Use parameterized queries.** Never generate raw SQL string concatenation that could lead to SQL injection.
- **Apply least privilege.** Generated authentication and authorization logic must follow the principle of least privilege.
---
 
## 9. Communication Standards
 
When presenting generated code, the AI must always include:
 
1. **Summary** — A plain-language explanation of what the code does.
2. **Impact Assessment** — Which parts of the system (backend, Flutter, Next.js) are affected.
3. **Risk Level** — `LOW` / `MEDIUM` / `HIGH` based on the scope of change.
4. **Warnings** — Any potential side effects, deprecations, or breaking changes.
5. **Next Steps** — What the developer should test or verify after applying the change.
---
 
## 10. Prohibited Actions
 
The AI must **never** do the following without explicit written approval from the developer:
 
| #  | Prohibited Action |
|----|-------------------|
| 1  | Modify or delete existing API route handlers in `routes/api.php` |
| 2  | Change database schema without creating a new migration file |
| 3  | Modify the `toArray()` method of any existing Laravel API Resource |
| 4  | Rename or remove any field exposed in an existing API response |
| 5  | Alter authentication/authorization middleware or Sanctum/Passport config |
| 6  | Change CORS configuration (`config/cors.php`) |
| 7  | Remove or rename exported types/interfaces used by Next.js |
| 8  | Modify `next.config.js` or any Next.js build configuration |
| 9  | Change Flutter's `pubspec.yaml` packages without noting version compatibility |
| 10 | Suggest Laravel 9+ features, syntax, or packages on this Laravel 8 project |
| 11 | Write code that bypasses input validation (`FormRequest`) for "convenience" |
| 12 | Generate placeholder or stub code without clearly labeling it as such |
 
---
 
## 11. Labeling Convention
 
All AI-generated code blocks must use the following inline comment labels where applicable:
 
```
// [AI-GENERATED] - Review before committing
// [MOBILE ONLY] - Safe for Flutter, no impact on Next.js or Laravel shared logic
// [WEB ONLY] - Safe for Next.js, no impact on Flutter
// [BACKEND ONLY] - Laravel change, verify impact on BOTH frontends before applying
// [SHARED] - Affects both frontends, review carefully
// [BREAKING CHANGE] - Requires coordinated update on all frontends
// [LARAVEL 8 ONLY] - Uses L8-specific syntax, not forward compatible
// [TODO] - Placeholder, must be implemented before production
// [MIGRATION REQUIRED] - Requires a new database migration to take effect
// [NEXT.JS IMPACT] - This backend change may affect the Next.js frontend
```
 
---
 
## 12. Final Reminder
 
> This is a **capstone project** with a **Laravel 8 backend** serving both a **Flutter mobile app** and a **Next.js web app**.
>
> The active developer works on the **Flutter (mobile)** side. Backend changes requested to support Flutter features must always be evaluated for their impact on the **Next.js web frontend** — which is **not** under active development and must not be broken.
>
> The **Next.js web frontend** is the most sensitive surface in this architecture. The **Laravel 8 backend** is the most likely source of accidental breakage.
>
> When in doubt — **ask first, code second.**
 
---
 
*Last updated: April 2026*