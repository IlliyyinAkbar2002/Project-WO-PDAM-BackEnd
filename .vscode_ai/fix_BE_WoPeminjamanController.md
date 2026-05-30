# Bug Report: Undefined Column 'name' on Users Table in WoPeminjamanMaterialController

This document summarizes the root cause of the `500 Internal Server Error` returned by the backend when listing material borrowings, and outlines the fix to be applied by the backend agent.

---

## 1. Problem Summary
When a GET request is sent to:
```
GET /api/v1/workorder/{workorder_id}/peminjaman-material
```
The server crashes with a **`500 Internal Server Error`** due to a database query exception: **column "name" does not exist on the users table**.

---

## 2. Root Cause

1. In the backend controller `app/Http/Controllers/WoPeminjamanMaterialController.php` (line 21-27), the `index` method eager loads the relations using:
   ```php
   $peminjaman = WoPeminjamanMaterial::with([
       'material:kode_material,nama,satuan',
       'pengaju:id,name,pegawai_id',      // <-- requests 'name' from users table
       'pengaju.pegawai:id,nama,nip',
       'verifier:id,name,pegawai_id',    // <-- requests 'name' from users table
       'verifier.pegawai:id,nama,nip',
   ])
   ```

2. However, in the database migration `database/migrations/2025_03_08_145008_create_users_table.php`, the `name` column on the `users` table is commented out:
   ```php
   // $table->string('name');
   ```
   Instead, user names are stored inside the `m_pegawai` table (which is correctly loaded via the nested `.pegawai` relationship).

3. When executing the index query, the database engine throws:
   ```
   SQLSTATE[42703]: Undefined column: 7 ERROR: column "name" does not exist
   LINE 1: select "id", "name", "pegawai_id" from "users" where "users"...
   ```

---

## 3. Proposed Fix
Modify the relations eager loading block in `app/Http/Controllers/WoPeminjamanMaterialController.php` under the `index` method to select only the existent fields (`id` and `pegawai_id`) from the `users` table:

```php
    public function index($workorder_id)
    {
        $peminjaman = WoPeminjamanMaterial::with([
            'material:kode_material,nama,satuan',
            'pengaju:id,pegawai_id',
            'pengaju.pegawai:id,nama,nip',
            'verifier:id,pegawai_id',
            'verifier.pegawai:id,nama,nip',
        ])
            ->where('workorder_id', $workorder_id)
            ->orderByDesc('diajukan_at')
            ->get();

        return response()->json([
            'message' => 'Data peminjaman material berhasil diambil.',
            'data'    => $peminjaman,
        ]);
    }
```
