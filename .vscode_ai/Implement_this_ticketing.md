
**Cursor**

Oke, aku susun **dua-duanya** dalam satu respons supaya tim backend tinggal copy-paste. Aku pisahkan jadi 3 bagian:

- **Bagian A** — Roadmap & urutan eksekusi (penting biar tidak tabrakan FK).
- **Bagian B** — Markdown ticket (siap dimasukkan ke Jira/GitHub Issues).
- **Bagian C** — Draft kode migration + perubahan service/controller/model (siap di-review).

> Catatan: semua draft di bawah aku tampilkan sebagai **markdown code block** karena belum ada di codebase. Kamu / tim backend bebas adaptasi.

---

# Bagian A — Roadmap & Urutan Eksekusi

Karena ada perubahan besar (drop kolom + bikin pivot), urutan migration **tidak boleh acak**. Saran urutan:

| Urutan | Ticket | Sifat | Risiko |
|---|---|---|---|
| 1 | **TKT-01** Tambah `kode` di `m_action` (Q4) | Aditif | Rendah |
| 2 | **TKT-02** Buat tabel `m_tipe_progress` (Q5) | Aditif | Rendah |
| 3 | **TKT-03** Tambah `status_id` di `progress_workorder` (Q3) | Aditif | Rendah |
| 4 | **TKT-04** Tambah `submitted_by_user_id` di `progress_workorder` (konsekuensi Q1) | Aditif | Rendah |
| 5 | **TKT-05** Migrasi `progress_workorder.tipe_progress` → `tipe_progress_id` (Q5 lanjut) | Backfill + drop kolom lama | Sedang |
| 6 | **TKT-06** Tambah `actor_id` di `workorder_action` (Q2) | Aditif (nullable dulu, lalu strict) | Rendah |
| 7 | **TKT-07** Pindah `workorder.petugas_id` → pivot `workorder_petugas` (Q1) | Drop kolom + bikin pivot + ubah service | **Tinggi** |

> Strategi anti-zero-downtime: **tiap migration aditif boleh langsung deploy**. Migration besar (TKT-05 & TKT-07) sebaiknya dipisah jadi **3 PR**: (1) tambah kolom baru + dual-write, (2) backfill data lama, (3) drop kolom lama + remove dual-write.
> Karena ini masih thesis/dev environment, kamu boleh skip strategi ini dan langsung 1 PR per ticket.

---

# Bagian B — Markdown Ticket (siap copy ke Jira / GitHub)

## TKT-01 · Tambah kolom `kode` (slug) di `m_action`

**Why**
Saat ini service layer mengidentifikasi master action dengan **id numerik magis** (`action_id == 1` untuk Penugasan, `== 2` untuk Freeze, dst). Ini rapuh: kalau master data pernah re-seed dengan urutan berbeda, logic `WorkorderActionService::handleFreeze()` akan bekerja untuk action yang salah. Sesuai keputusan Q4, master action akan bertambah, jadi identifier-nya wajib stabil.

**What**
- Tambah kolom `kode` (string, unique, nullable dulu) di `m_action`.
- Backfill kode untuk 4 row existing: `PENUGASAN`, `FREEZE`, `RESUME`, `EXTEND`.
- Set `kode` jadi `NOT NULL` setelah backfill.
- Refactor semua tempat yang membandingkan `action_id == N` → bandingkan via `kode`.

**Acceptance Criteria**
- [x] `m_action.kode` unique, not null, ada index.
- [x] `MasterActionFactory` mengisi `kode` random aman.
- [x] `WorkorderService::ensureDefaultActionExists()` mencari berdasarkan `kode = 'PENUGASAN'`.
- [x] `WorkorderActionService::processAction()` menggunakan `switch ($action->action->kode)` bukan `switch ((int) $action->action_id)`.
- [x] `Workorder::latestFreeze()` query `whereHas('action', fn($q) => $q->where('kode', 'FREEZE'))`.

**Files terdampak**
- `database/migrations/*_add_kode_to_master_actions_table.php` (baru)
- `database/factories/MasterActionFactory.php`
- `database/seeders/DatabaseSeeder.php`
- `app/Services/WorkorderService.php`
- `app/Services/WorkorderActionService.php`
- `app/Models/Workorder.php` (relasi `latestFreeze`)

---

## TKT-02 · Buat tabel master `m_tipe_progress`

**Why**
`progress_workorder.tipe_progress` saat ini string bebas (`'Mulai'`, `'Selesai'`, `'Progress 1'`, `'Progress 2'`, ...). Logika `ProgressWorkorderService::updateStatusOnSubmit()` membandingkan `=== 'Mulai'` / `=== 'Selesai'` — typo / locale = bug senyap. Sesuai Q5, butuh validasi ketat.

**What**
Buat tabel master 3 row:

| id | kode | nama |
|---|---|---|
| 1 | `MULAI` | Mulai |
| 2 | `PROGRESS` | Progress |
| 3 | `SELESAI` | Selesai |

> Note: untuk progres ke-N (sekarang `'Progress 1'`, `'Progress 2'`...), urutan ditentukan kolom `order` yang sudah ada — tidak perlu disimpan di nama lagi.

**Acceptance Criteria**
- [x] Tabel `m_tipe_progress` ter-seed otomatis 3 row di atas.
- [x] Ada `TipeProgress` model + relasi `hasMany(ProgressWorkorder::class, 'tipe_progress_id')`.
- [x] Tidak ada perubahan kolom di `progress_workorder` di ticket ini (itu di TKT-05).

**Files baru**
- `database/migrations/*_create_m_tipe_progress_table.php`
- `database/seeders/TipeProgressSeeder.php`
- `app/Models/TipeProgress.php`

---

## TKT-03 · Tambah `status_id` di `progress_workorder` (Q3)

**Why**
- Saat ini tidak ada cara membedakan progres yang **draft** (row sudah dibuat saat assign tapi belum disubmit) vs **submitted** vs **verified**. Status implisit lewat `waktu_submit IS NULL` kurang ekspresif.
- Model `Status::progressWorkorder()` saat ini menyatakan `hasMany(ProgressWorkorder::class, 'status_id')` padahal kolomnya **tidak ada** di migration → bom waktu silent error.

**What**
- Tambah kolom `status_id` (FK `m_status`, nullable) di `progress_workorder`.
- Tambah row di `m_status` (atau pakai status existing) untuk semantik progres: `DRAFT`, `SUBMITTED`, `VERIFIED`. Kalau kolom `kode` di `m_status` belum ada, buat ticket terpisah TKT-03b untuk tambah `kode` di `m_status` (skenario serupa Q4).
- Default value saat `createInitialProgress()`: status `DRAFT`.
- Saat `updateStatusOnSubmit()`: ubah status progres jadi `SUBMITTED`.
- (Opsional) Saat SPV approve: `VERIFIED`.

**Acceptance Criteria**
- [x] Kolom `progress_workorder.status_id` ada FK ke `m_status`.
- [x] Relasi `ProgressWorkorder::status()` ada.
- [x] `createInitialProgress()` mengisi `status_id` ke kode `DRAFT`.
- [x] `updateStatusOnSubmit()` mengubah status progres jadi `SUBMITTED` (sebelum mengubah status workorder seperti sekarang).

---

## TKT-04 · Tambah `submitted_by_user_id` di `progress_workorder` (konsekuensi Q1)

**Why**
Q1 = 1 WO bisa dikerjakan banyak petugas. Tanpa kolom ini, kalau Senior Staff dan Staff sama-sama submit progres untuk WO yang sama, tidak ada cara tahu siapa submit yang mana. Audit trail per progres = wajib.

**What**
- Tambah `submitted_by_user_id` (FK `users`, nullable — nullable karena saat row dibuat oleh `createInitialProgress()` belum ada submitter).
- Saat `ProgressWorkorderController::update()` dipanggil, isi dengan `auth()->id()`.

**Acceptance Criteria**
- [x] `progress_workorder.submitted_by_user_id` FK ke `users`.
- [x] Relasi `ProgressWorkorder::submitter()` (nama hindari clash dengan `petugas`).
- [x] `update()` controller mengisi otomatis dari auth user.

---

## TKT-05 · Migrasi `progress_workorder.tipe_progress` (string) → `tipe_progress_id` (FK)

**Why**
Lanjutan TKT-02. Validasi ketat & menghilangkan branching by string.

**What** (3 langkah aman)
1. Tambah kolom `tipe_progress_id` (FK `m_tipe_progress`, nullable).
2. Backfill: row dengan `tipe_progress = 'Mulai'` → id 1; `'Selesai'` → id 3; selain itu (`'Progress N'`) → id 2.
3. Set `NOT NULL`, drop kolom `tipe_progress` lama.

**Acceptance Criteria**
- [x] Tidak ada lagi referensi string `'Mulai'`/`'Selesai'`/`'Progress N'` di codebase (cek `rg "tipe_progress"`).
- [x] `ProgressWorkorderService::createInitialProgress()` mengisi `tipe_progress_id` ke FK.
- [x] `addWorkorderProgress()` mengisi `tipe_progress_id` ke id `PROGRESS`.
- [x] `updateStatusOnSubmit()` membandingkan via relasi: `$progress->tipeProgress->kode === 'MULAI'`.

---

## TKT-06 · Tambah `actor_id` di `workorder_action` (Q2)

**Why**
Audit trail: siapa yang freeze, siapa yang resume, siapa yang extend. Saat ini hilang.

**What**
- Tambah `actor_id` (FK `users`, nullable dulu untuk kompatibilitas data lama).
- Setiap insert via `WorkorderActionService::createAction()` wajib include `actor_id` dari `auth()->id()`.
- Saat `WorkorderService::createWorkorders()` membuat action `PENUGASAN`, `actor_id` = SPV (`pic_id`).
- Validator di `WorkorderActionController::store()` tidak perlu menerima `actor_id` dari client — selalu inject dari `$request->user()->id`.

**Acceptance Criteria**
- [x] `workorder_action.actor_id` FK ke `users`.
- [x] Relasi `WorkorderAction::actor()`.
- [x] Tidak ada path code yang insert `workorder_action` tanpa `actor_id` (kecuali `actor_id` legacy data lama).
- [x] Endpoint `POST /workorder-action` mengabaikan `actor_id` di body, selalu pakai auth.

---

## TKT-07 · Pindah `workorder.petugas_id` → pivot `workorder_petugas` (Q1) **[BREAKING]**

**Why**
Sesuai Q1: 1 WO = banyak petugas. Skema sekarang single-FK + workaround "bikin N row WO" merusak laporan dan bikin assignment tidak bisa di-update sebagai satu kesatuan.

**What** (3 langkah aman)
1. Buat tabel pivot `workorder_petugas`:
   ```text
   id, workorder_id (FK), user_id (FK users), peran (string nullable), timestamps
   unique (workorder_id, user_id)
   ```
2. Backfill: untuk setiap `workorder` lama, insert satu row pivot `(workorder_id, petugas_id)`.
3. Drop kolom `workorder.petugas_id`.

**Yang berubah di service & controller:**
- `WorkorderController::store()` validator tetap `petugas_id` array — tapi service **tidak lagi looping** membuat N row workorder.
- `WorkorderService::createWorkorders()` membuat **1 row workorder**, lalu **N row workorder_petugas**, lalu **1 row workorder_action (PENUGASAN)**, lalu progres awal (Mulai + Selesai).
- `WorkorderController::index()` filter "WO yang ditugaskan ke saya" berubah:
  - Dari `->orWhere('petugas_id', $user->id)` jadi `->orWhereHas('petugasList', fn($q) => $q->where('users.id', $user->id))`.
- Eager load `with('petugas', ...)` jadi `with('petugasList.pegawai', ...)`.

**Acceptance Criteria**
- [x] Tabel pivot `workorder_petugas` ada + unique constraint.
- [x] `Workorder::petugasList()` `belongsToMany`.
- [x] Submit 1 form Ajukan dengan 3 petugas → 1 row `workorder` + 3 row `workorder_petugas`.
- [x] Dashboard SPV "WO yang saya buat" tetap akurat (jumlah tidak meledak jadi N).
- [x] List WO "ditugaskan ke saya" untuk Staff tetap akurat.
- [x] Frontend Flutter **tidak perlu diubah** payload-nya (tetap kirim `petugas_id` sebagai array of int).
- [x] Frontend Web (Next.js) — koordinasi dengan teman web FE: response sekarang punya `petugas_list` (array), bukan `petugas` (object). **Breaking untuk web FE.**

---

# Bagian C — Draft Kode

## TKT-01 · `m_action.kode`

**Migration baru**

```php
<?php
// database/migrations/2026_04_19_120000_add_kode_to_master_actions_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AddKodeToMasterActionsTable extends Migration
{
    public function up()
    {
        Schema::table('m_action', function (Blueprint $table) {
            $table->string('kode', 32)->nullable()->after('id');
        });

        $map = [
            'Penugasan' => 'PENUGASAN',
            'Freeze'    => 'FREEZE',
            'Resume'    => 'RESUME',
            'Extend'    => 'EXTEND',
        ];
        foreach ($map as $nama => $kode) {
            DB::table('m_action')->where('nama', $nama)->update(['kode' => $kode]);
        }

        Schema::table('m_action', function (Blueprint $table) {
            $table->string('kode', 32)->nullable(false)->change();
            $table->unique('kode');
        });
    }

    public function down()
    {
        Schema::table('m_action', function (Blueprint $table) {
            $table->dropUnique(['kode']);
            $table->dropColumn('kode');
        });
    }
}
```

**Service refactor**

```php
// app/Services/WorkorderService.php
private function ensureDefaultActionExists(): int
{
    $action = MasterAction::firstOrCreate(
        ['kode' => 'PENUGASAN'],
        ['nama' => 'Penugasan', 'keterangan' => 'Penugasan kepada pegawai']
    );
    return (int) $action->id;
}
```

```php
// app/Services/WorkorderActionService.php
public function processAction(array $data)
{
    return DB::transaction(function () use ($data) {
        $workorder = Workorder::findOrFail($data['workorder_id']);
        $action    = WorkorderAction::create($data);

        switch ($action->action->kode) {
            case 'FREEZE':
                $this->handleFreeze($action, $workorder, $data);
                break;
            case 'RESUME':
                $this->handleResume($action, $workorder, $data);
                break;
            case 'EXTEND':
                $this->handleExtend($action, $workorder, $data);
                break;
        }
        return $action;
    });
}
```

```php
// app/Models/Workorder.php
public function latestFreeze()
{
    return $this->hasOne(WorkorderAction::class, 'workorder_id')
        ->whereHas('action', fn ($q) => $q->where('kode', 'FREEZE'))
        ->latest();
}
```

---

## TKT-02 · Tabel `m_tipe_progress`

**Migration**

```php
<?php
// database/migrations/2026_04_19_120100_create_m_tipe_progress_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CreateMTipeProgressTable extends Migration
{
    public function up()
    {
        Schema::create('m_tipe_progress', function (Blueprint $table) {
            $table->id();
            $table->string('kode', 32)->unique();
            $table->string('nama');
            $table->timestamps();
        });

        DB::table('m_tipe_progress')->insert([
            ['id' => 1, 'kode' => 'MULAI',    'nama' => 'Mulai',    'created_at' => now(), 'updated_at' => now()],
            ['id' => 2, 'kode' => 'PROGRESS', 'nama' => 'Progress', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 3, 'kode' => 'SELESAI',  'nama' => 'Selesai',  'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    public function down()
    {
        Schema::dropIfExists('m_tipe_progress');
    }
}
```

**Model**

```php
<?php
// app/Models/TipeProgress.php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TipeProgress extends Model
{
    protected $table = 'm_tipe_progress';
    protected $guarded = [];

    public function progressWorkorder()
    {
        return $this->hasMany(ProgressWorkorder::class, 'tipe_progress_id');
    }
}
```

---

## TKT-03 · `progress_workorder.status_id`

**Migration**

```php
<?php
// database/migrations/2026_04_19_120200_add_status_id_to_progress_workorders_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddStatusIdToProgressWorkordersTable extends Migration
{
    public function up()
    {
        Schema::table('progress_workorder', function (Blueprint $table) {
            $table->foreignId('status_id')
                  ->nullable()
                  ->after('hasil_pengerjaan')
                  ->constrained('m_status');
        });
    }

    public function down()
    {
        Schema::table('progress_workorder', function (Blueprint $table) {
            $table->dropConstrainedForeignId('status_id');
        });
    }
}
```

**Model**

```php
// app/Models/ProgressWorkorder.php (tambahan)
public function status()
{
    return $this->belongsTo(Status::class, 'status_id');
}
```

> Pastikan `m_status` punya row untuk progres (`DRAFT`, `SUBMITTED`, `VERIFIED`) — buat seeder atau pakai status workorder existing yang relevan.

---

## TKT-04 · `progress_workorder.submitted_by_user_id`

**Migration**

```php
<?php
// database/migrations/2026_04_19_120300_add_submitted_by_to_progress_workorders_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddSubmittedByToProgressWorkordersTable extends Migration
{
    public function up()
    {
        Schema::table('progress_workorder', function (Blueprint $table) {
            $table->foreignId('submitted_by_user_id')
                  ->nullable()
                  ->after('status_id')
                  ->constrained('users');
        });
    }

    public function down()
    {
        Schema::table('progress_workorder', function (Blueprint $table) {
            $table->dropConstrainedForeignId('submitted_by_user_id');
        });
    }
}
```

**Model + Controller**

```php
// app/Models/ProgressWorkorder.php
public function submitter()
{
    return $this->belongsTo(User::class, 'submitted_by_user_id')
        ->with(['pegawai:id,nama,nip']);
}
```

```php
// app/Http/Controllers/ProgressWorkorderController.php (di dalam update())
$progressWorkorder->update([
    'waktu_submit'         => now(),
    'hasil_pengerjaan'     => $validatedData['hasil_pengerjaan'],
    'submitted_by_user_id' => $request->user()->id,
]);
```

---

## TKT-05 · `tipe_progress` → `tipe_progress_id`

**Migration (1 file, 3 step)**

```php
<?php
// database/migrations/2026_04_19_120400_convert_tipe_progress_to_fk_in_progress_workorders_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ConvertTipeProgressToFkInProgressWorkordersTable extends Migration
{
    public function up()
    {
        Schema::table('progress_workorder', function (Blueprint $table) {
            $table->foreignId('tipe_progress_id')
                  ->nullable()
                  ->after('workorder_id')
                  ->constrained('m_tipe_progress');
        });

        DB::table('progress_workorder')->where('tipe_progress', 'Mulai')->update(['tipe_progress_id' => 1]);
        DB::table('progress_workorder')->where('tipe_progress', 'Selesai')->update(['tipe_progress_id' => 3]);
        DB::table('progress_workorder')
            ->whereNotIn('tipe_progress', ['Mulai', 'Selesai'])
            ->update(['tipe_progress_id' => 2]);

        Schema::table('progress_workorder', function (Blueprint $table) {
            $table->foreignId('tipe_progress_id')->nullable(false)->change();
            $table->dropColumn('tipe_progress');
        });
    }

    public function down()
    {
        Schema::table('progress_workorder', function (Blueprint $table) {
            $table->string('tipe_progress')->nullable();
        });
        DB::table('progress_workorder')->where('tipe_progress_id', 1)->update(['tipe_progress' => 'Mulai']);
        DB::table('progress_workorder')->where('tipe_progress_id', 3)->update(['tipe_progress' => 'Selesai']);
        DB::table('progress_workorder')->where('tipe_progress_id', 2)->update(['tipe_progress' => 'Progress']);
        Schema::table('progress_workorder', function (Blueprint $table) {
            $table->dropConstrainedForeignId('tipe_progress_id');
        });
    }
}
```

**Service refactor**

```php
// app/Services/ProgressWorkorderService.php
use App\Models\TipeProgress;

public function createInitialProgress(int $workOrderId): void
{
    $tipeMulai   = TipeProgress::where('kode', 'MULAI')->value('id');
    $tipeSelesai = TipeProgress::where('kode', 'SELESAI')->value('id');
    $statusDraft = \App\Models\Status::where('nama', 'Draft')->value('id'); // sesuaikan

    ProgressWorkorder::create([
        'workorder_id'     => $workOrderId,
        'tipe_progress_id' => $tipeMulai,
        'status_id'        => $statusDraft,
        'order'            => 0,
    ]);

    $progressSelesai = ProgressWorkorder::create([
        'workorder_id'     => $workOrderId,
        'tipe_progress_id' => $tipeSelesai,
        'status_id'        => $statusDraft,
        'order'            => 1,
    ]);

    $workorder = Workorder::with('jenisWorkorder.detailForm')->findOrFail($workOrderId);
    foreach ($workorder->jenisWorkorder->detailForm as $detailForm) {
        DetailProgress::create([
            'progress_workorder_id' => $progressSelesai->id,
            'detail_form_id'        => $detailForm->id,
            'value'                 => '',
        ]);
    }
}

public function addWorkorderProgress(int $workOrderId)
{
    $tipeProgress = TipeProgress::where('kode', 'PROGRESS')->value('id');
    $statusDraft  = \App\Models\Status::where('nama', 'Draft')->value('id');

    $maxOrder = ProgressWorkorder::where('workorder_id', $workOrderId)->max('order') ?? 0;

    ProgressWorkorder::where('workorder_id', $workOrderId)
        ->where('order', $maxOrder)
        ->increment('order');

    ProgressWorkorder::create([
        'workorder_id'     => $workOrderId,
        'tipe_progress_id' => $tipeProgress,
        'status_id'        => $statusDraft,
        'order'            => $maxOrder,
    ]);

    return response()->json(['message' => 'Progress ditambahkan'], 201);
}

public function updateStatusOnSubmit(int $progressId): void
{
    $progress = ProgressWorkorder::with(['workorder', 'tipeProgress'])->findOrFail($progressId);
    $kode = $progress->tipeProgress->kode;

    if ($kode === 'MULAI') {
        $progress->workorder->update(['status_id' => 7]);
    } elseif ($kode === 'SELESAI') {
        $progress->workorder->update(['status_id' => 5]);
    }
}
```

---

## TKT-06 · `workorder_action.actor_id`

**Migration**

```php
<?php
// database/migrations/2026_04_19_120500_add_actor_id_to_workorder_actions_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddActorIdToWorkorderActionsTable extends Migration
{
    public function up()
    {
        Schema::table('workorder_action', function (Blueprint $table) {
            $table->foreignId('actor_id')
                  ->nullable()
                  ->after('action_id')
                  ->constrained('users');
        });
    }

    public function down()
    {
        Schema::table('workorder_action', function (Blueprint $table) {
            $table->dropConstrainedForeignId('actor_id');
        });
    }
}
```

**Model + Controller + Service**

```php
// app/Models/WorkorderAction.php
public function actor()
{
    return $this->belongsTo(User::class, 'actor_id')->with(['pegawai:id,nama,nip']);
}
```

```php
// app/Http/Controllers/WorkorderActionController.php
public function store(Request $request)
{
    $validatedData = $request->validate([
        'workorder_id'      => 'required|exists:workorder,id',
        'action_id'         => 'required|exists:m_action,id',
        'keterangan'        => 'nullable|string|max:255',
        'waktu_mulai'       => 'required|date',
        'sisa_durasi_menit' => 'nullable|integer',
        'estimasi_selesai'  => 'nullable|date',
    ]);
    $validatedData['actor_id'] = $request->user()->id;

    try {
        $action = (new WorkorderActionService())->createAction($validatedData);
        return response()->json(['message' => 'Workorder action created successfully', 'data' => $action], 201);
    } catch (\Exception $e) {
        return response()->json(['error' => 'Failed to create workorder action', 'message' => $e->getMessage()], 500);
    }
}
```

```php
// app/Services/WorkorderService.php (saat membuat PENUGASAN)
(new WorkorderActionService())->createAction([
    'workorder_id'     => $workorder->id,
    'action_id'        => $penugasanActionId,
    'actor_id'         => $data['pic_id'],
    'keterangan'       => 'Penugasan awal',
    'waktu_mulai'      => $data['waktu_penugasan'],
    'estimasi_selesai' => $data['estimasi_selesai'],
]);
```

---

## TKT-07 · Pivot `workorder_petugas` (Q1 — paling besar)

**Migration A — buat pivot**

```php
<?php
// database/migrations/2026_04_19_120600_create_workorder_petugas_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateWorkorderPetugasTable extends Migration
{
    public function up()
    {
        Schema::create('workorder_petugas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workorder_id')->constrained('workorder')->onDelete('cascade');
            $table->foreignId('user_id')->constrained('users');
            $table->string('peran')->nullable();
            $table->timestamps();

            $table->unique(['workorder_id', 'user_id']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('workorder_petugas');
    }
}
```

**Migration B — backfill + drop kolom**

```php
<?php
// database/migrations/2026_04_19_120700_backfill_and_drop_petugas_id_on_workorder_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class BackfillAndDropPetugasIdOnWorkorderTable extends Migration
{
    public function up()
    {
        DB::statement("
            INSERT INTO workorder_petugas (workorder_id, user_id, created_at, updated_at)
            SELECT id, petugas_id, created_at, updated_at FROM workorder
            WHERE petugas_id IS NOT NULL
            ON CONFLICT (workorder_id, user_id) DO NOTHING
        ");

        Schema::table('workorder', function (Blueprint $table) {
            $table->dropConstrainedForeignId('petugas_id');
        });
    }

    public function down()
    {
        Schema::table('workorder', function (Blueprint $table) {
            $table->foreignId('petugas_id')->nullable()->constrained('users');
        });

        DB::statement("
            UPDATE workorder w SET petugas_id = wp.user_id
            FROM (
                SELECT DISTINCT ON (workorder_id) workorder_id, user_id
                FROM workorder_petugas ORDER BY workorder_id, id ASC
            ) wp
            WHERE w.id = wp.workorder_id
        ");
    }
}
```

**Model `Workorder` — ganti relasi**

```php
// app/Models/Workorder.php
public function petugasList()
{
    return $this->belongsToMany(User::class, 'workorder_petugas', 'workorder_id', 'user_id')
        ->withPivot('peran')
        ->withTimestamps()
        ->with(['pegawai:id,nama,nip']);
}

// HAPUS method petugas() yang lama
```

**Service — simplify (1 row workorder, N row pivot)**

```php
// app/Services/WorkorderService.php
public function createWorkorders(array $data)
{
    return DB::transaction(function () use ($data) {
        $penugasanActionId = $this->ensureDefaultActionExists();

        $lemburSplId = null;
        $statusId    = 2;

        if ((int) $data['tipe_workorder_id'] === 2) {
            $lemburSpl   = LemburSpl::create(['status_id' => 1, 'waktu_pengajuan' => now()]);
            $lemburSplId = $lemburSpl->id;
            $statusId    = 1;
        }

        $workorder = Workorder::create([
            'judul_pekerjaan'    => $data['judul_pekerjaan'],
            'waktu_penugasan'    => $data['waktu_penugasan'],
            'estimasi_durasi'    => $data['estimasi_durasi'],
            'unit_waktu'         => $data['unit_waktu'],
            'estimasi_selesai'   => $data['estimasi_selesai'],
            'longitude'          => $data['longitude'] ?? null,
            'latitude'           => $data['latitude'] ?? null,
            'location_id'        => $data['location_id'] ?? null,
            'pic_id'             => $data['pic_id'],
            'lembur_spl_id'      => $lemburSplId,
            'status_id'          => $statusId,
            'jenis_workorder_id' => $data['jenis_workorder_id'],
            'jenis_lokasi_id'    => $data['jenis_lokasi_id'],
            'tipe_workorder_id'  => $data['tipe_workorder_id'],
        ]);

        $workorder->petugasList()->attach($data['petugas_id']);

        if ($statusId === 2) {
            (new ProgressWorkorderService())->createInitialProgress($workorder->id);
            (new WorkorderActionService())->createAction([
                'workorder_id'     => $workorder->id,
                'action_id'        => $penugasanActionId,
                'actor_id'         => $data['pic_id'],
                'keterangan'       => 'Penugasan awal',
                'waktu_mulai'      => $data['waktu_penugasan'],
                'estimasi_selesai' => $data['estimasi_selesai'],
            ]);
        }

        return $workorder->load('petugasList', 'pic', 'status', 'jenisWorkorder', 'jenisLokasi', 'tipeWorkorder');
    });
}
```

**Controller index — ubah filter**

```php
// app/Http/Controllers/WorkorderController.php (potongan index)
$query = Workorder::with('petugasList', 'pic', 'jenisWorkorder', 'jenisLokasi', 'tipeWorkorder', 'status', 'lemburSpl', 'location');

$user = $request->user();
if ($user->role_id != 1) {
    $query->where(function ($q) use ($user) {
        $q->where('pic_id', $user->id)
          ->orWhereHas('petugasList', fn ($qq) => $qq->where('users.id', $user->id));
    });
}

// search by petugas
->orWhereHas('petugasList.pegawai', fn ($q) =>
    $q->where('nama', 'ILIKE', "%{$search}%")->orWhere('nip', 'ILIKE', "%{$search}%")
)

// filter ?user_id=
if ($userId) {
    $query->whereHas('petugasList', fn ($q) => $q->where('users.id', $userId));
}
```

---

# Bagian D — Ticket Lanjutan (Q11–Q14)

> Ticket ini inkorporasi keputusan diskusi ERD Logical (Q11–Q14). Urutan eksekusi **harus diikuti** karena ada dependency antar migration (FK).
>
> Ringkas ticket baru:
>
> | Urutan | Ticket | Sifat | Dependency |
> |---|---|---|---|
> | 8 | **TKT-08** Validation 500 → 422 (DX fix) | Aditif | — |
> | 9 | **TKT-09** Departemen: 2 row tetap + seeder eksplisit | Refactor master data | — |
> | 10 | **TKT-10** Master data tambahan (m_status, m_tipe_progress, m_action kode baru) | Aditif row master | TKT-01, 02 |
> | 11 | **TKT-11** Tabel `pengaduan` + `m_jenis_pengaduan` | Aditif table | TKT-09 |
> | 12 | **TKT-12** `form_template` + `workorder.form_values JSONB` + drop EAV | **Breaking** (drop EAV) | TKT-05 |
> | 13 | **TKT-13** Workorder: `departemen_id`, `manager_id`, `approved_by_user_id`, `approved_at`, `approval_notes`, `pengaduan_id`, `created_by_user_id` | Aditif kolom | TKT-11 |
> | 14 | **TKT-14** Progress: `reviewed_by_user_id`, `reviewed_at`, `alasan_penolakan`, `field_to_revise JSONB` + append pattern | Aditif kolom + logic | TKT-10 |
> | 15 | **TKT-15** Tabel `laporan_workorder` + event listener + PDF | Aditif table + feature | TKT-13, 14 |
> | 16 | **TKT-16** Policy Manager–Departemen (Q11) | Logic enforcement | TKT-13 |

---

## TKT-08 · Laravel ValidationException handler → 422 dengan body standar

**Why**
Saat ini `FormRequest` / `validate()` yang gagal di-render oleh `App\Exceptions\Handler` jadi 500 dengan pesan generik "The given data was invalid." — FE Flutter & Next.js tidak bisa parse error per-field. Standar Laravel sendiri sudah kirim 422; masalah ada di handler yang meng-override.

**What**
- Audit `app/Exceptions/Handler.php`. Pastikan `ValidationException` tidak masuk ke branch "convert semua exception jadi 500".
- Tambah explicit render:
  ```php
  $this->renderable(function (ValidationException $e, $request) {
      if ($request->expectsJson() || $request->is('api/*')) {
          return response()->json([
              'message' => $e->getMessage(),
              'errors'  => $e->errors(),
          ], 422);
      }
  });
  ```
- Tambah renderable untuk `AuthenticationException` → 401, `AuthorizationException` → 403, `ModelNotFoundException` → 404 dengan body standar.

**Acceptance Criteria**
- [ ] `POST /api/v1/workorder` dengan body invalid → HTTP 422, body `{message, errors: {field: [msg...]}}`.
- [ ] `POST /api/v1/workorder` tanpa token → HTTP 401, body `{message: 'Unauthenticated.'}`.
- [ ] `GET /api/v1/workorder/999999` → HTTP 404, body `{message: 'Resource not found.'}`.
- [ ] Flutter `ApiClient.handleError()` bisa parse `errors.judul_pekerjaan[0]`.

**Files terdampak**
- `app/Exceptions/Handler.php`

---

## TKT-09 · Departemen: kerucutkan ke 2 row + seeder eksplisit

**Why**
Keputusan Q11 — cukup 2 departemen (`Operasional`, `Pelayanan`). Departemen adalah **master data deterministik**, bukan data random → pakai `Seeder` eksplisit, bukan `Factory`. Ini juga menghindari masalah `static $names` di factory yang state-nya tidak reset antar run.

**What**
- Buat `DepartemenSeeder` dengan `updateOrCreate` + id eksplisit:
  ```php
  Departemen::updateOrCreate(['id' => 1], ['nama' => 'Operasional']);
  Departemen::updateOrCreate(['id' => 2], ['nama' => 'Pelayanan']);
  ```
- Ganti di `DatabaseSeeder::run()`:
  - Dari: `Departemen::factory(3)->create();`
  - Menjadi: `$this->call(DepartemenSeeder::class);`
- Hapus `DepartemenFactory.php` (atau tandai deprecated) karena tidak dipakai lagi.
- Kalau DB dev sudah terlanjur punya row `Keuangan` (id=3), tambah instruksi cleanup manual di README: `php artisan migrate:fresh --seed`.

**Acceptance Criteria**
- [ ] Tabel `m_departemen` setelah seed hanya punya 2 row.
- [ ] Id departemen stabil (1 = Operasional, 2 = Pelayanan) supaya seeder pegawai yang refer `departemen_id` tidak pecah.
- [ ] `DepartemenFactory` di-rename atau dihapus.

**Files terdampak**
- `database/seeders/DepartemenSeeder.php` (baru)
- `database/seeders/DatabaseSeeder.php`
- `database/factories/DepartemenFactory.php` (hapus)

---

## TKT-10 · Master data tambahan untuk flow revisi, reject, approval

**Why**
Flow baru (Q5 — Manager approve, Q12 — SPV Revisi/Tolak) membutuhkan row master baru di `m_status`, `m_tipe_progress`, `m_action`. Tanpa row ini, service layer akan hardcode nilai yang tidak ter-enforce di DB.

**What**

### 10a. Row baru di `m_status` (via TKT-03b punya kolom `kode`):

| kode | nama | dipakai untuk |
|---|---|---|
| `DITUGASKAN_KE_SPV` | Ditugaskan ke SPV | workorder setelah Manager assign |
| `DITUGASKAN_KE_STAFF` | Ditugaskan ke Staff | workorder setelah SPV assign tim |
| `MENUNGGU_VERIFIKASI_SPV` | Menunggu Verifikasi SPV | workorder setelah Staff submit SELESAI |
| `MENUNGGU_APPROVAL_MANAGER` | Menunggu Approval Manager | workorder setelah SPV terima |
| `DITOLAK_SPV` | Ditolak SPV | workorder final, tidak lanjut |
| `DITOLAK_MANAGER` | Ditolak Manager | workorder balik ke pengerjaan |
| `REVISI_REQUESTED` | Revisi Diminta | progress_workorder — tanda SPV minta revisi |

### 10b. Row baru di `m_tipe_progress`:

| kode | nama |
|---|---|
| `REVISI` | Revisi (catatan SPV meminta revisi) |
| `DITOLAK` | Ditolak (SPV tolak final) |

### 10c. Row baru di `m_action`:

| kode | nama |
|---|---|
| `APPROVE` | Persetujuan Manager |
| `REJECT` | Penolakan Manager |
| `REVISI` | Permintaan Revisi dari SPV |
| `VERIFIKASI_SPV` | Verifikasi oleh SPV |

**Acceptance Criteria**
- [ ] `StatusSeeder` insert idempotent untuk 7 row baru.
- [ ] `TipeProgressSeeder` insert 2 row baru (total 5).
- [ ] `MasterActionFactory` / seeder insert 4 row baru.
- [ ] Tidak ada hardcoded id di service layer — semua pakai `where('kode', 'XXX')`.

**Files terdampak**
- `database/seeders/StatusSeeder.php`
- `database/seeders/TipeProgressSeeder.php`
- `database/factories/MasterActionFactory.php` atau `database/seeders/MasterActionSeeder.php` (baru)

---

## TKT-11 · Tabel `pengaduan` + `m_jenis_pengaduan`

**Why**
Q7 memutuskan pengaduan = table terpisah (1:N ke workorder). Sumber data saat ini dari seeder mock (Q10).

**What**

### 11a. Migration `m_jenis_pengaduan`:
```php
Schema::create('m_jenis_pengaduan', function (Blueprint $table) {
    $table->id();
    $table->string('kode', 32)->unique();
    $table->string('nama');
    $table->timestamps();
});

DB::table('m_jenis_pengaduan')->insert([
    ['kode' => 'AIR_KERUH',  'nama' => 'Air Keruh',  'created_at' => now(), 'updated_at' => now()],
    ['kode' => 'KEBOCORAN',  'nama' => 'Kebocoran',  'created_at' => now(), 'updated_at' => now()],
    ['kode' => 'METER',      'nama' => 'Meter',      'created_at' => now(), 'updated_at' => now()],
    ['kode' => 'PEMAKAIAN',  'nama' => 'Pemakaian',  'created_at' => now(), 'updated_at' => now()],
    ['kode' => 'TDA',        'nama' => 'Tidak Ada Air', 'created_at' => now(), 'updated_at' => now()],
    ['kode' => 'LAIN_LAIN',  'nama' => 'Lain-lain',  'created_at' => now(), 'updated_at' => now()],
]);
```

### 11b. Migration `pengaduan`:
```php
Schema::create('pengaduan', function (Blueprint $table) {
    $table->id();
    $table->string('external_id', 64)->unique()->nullable();
    $table->string('nomor_pelanggan', 32)->nullable();
    $table->string('nama_pelapor');
    $table->string('kontak_pelapor')->nullable();
    $table->text('alamat')->nullable();
    $table->foreignId('jenis_pengaduan_id')->constrained('m_jenis_pengaduan');
    $table->foreignId('status_id')->constrained('m_status');
    $table->foreignId('departemen_id')->nullable()->constrained('m_departemen');
    $table->foreignId('duplicate_of_id')->nullable()->constrained('pengaduan');
    $table->text('deskripsi');
    $table->dateTime('tanggal_lapor');
    $table->dateTime('fetched_at')->nullable();
    $table->jsonb('raw_payload')->nullable();
    $table->timestamps();

    $table->index(['status_id']);
    $table->index(['jenis_pengaduan_id']);
});
```

### 11c. Model + Seeder:
- `app/Models/Pengaduan.php` dengan relasi `jenisPengaduan()`, `status()`, `departemen()`, `duplicateOf()`, `workorders()`.
- `app/Models/JenisPengaduan.php`.
- `database/seeders/PengaduanSeeder.php` — insert 20 row realistis (mix dari 6 jenis, status bervariasi).
- Panggil dari `DatabaseSeeder` **setelah** master status + departemen.

**Acceptance Criteria**
- [ ] `m_jenis_pengaduan` ter-seed 6 row.
- [ ] `pengaduan` bisa di-seed 20 row tanpa error FK.
- [ ] Relasi `Pengaduan::workorders()` `hasMany(Workorder::class, 'pengaduan_id')` — dipakai setelah TKT-13.

**Files terdampak** (semua baru)
- `database/migrations/*_create_m_jenis_pengaduan_table.php`
- `database/migrations/*_create_pengaduan_table.php`
- `database/seeders/JenisPengaduanSeeder.php`
- `database/seeders/PengaduanSeeder.php`
- `app/Models/JenisPengaduan.php`
- `app/Models/Pengaduan.php`

---

## TKT-12 · Template-driven form + `workorder.form_values JSONB` **[BREAKING]**

**Why**
Keputusan Q1 — buang EAV (`detail_form` + `detail_progress`). Ganti dengan:
- `form_template` = skema field per jenis WO
- `workorder.form_values` = JSONB isian

Ini investasi refactor paling besar di ticket lanjutan. Ikuti 5-langkah aman biar data historis tidak hilang.

**What** (5 langkah)

### 12a. Buat tabel `form_template`
```php
Schema::create('form_template', function (Blueprint $table) {
    $table->id();
    $table->foreignId('jenis_workorder_id')->constrained('m_jenis_workorder')->onDelete('cascade');
    $table->string('nama_field', 64);   // slug mis. "diameter_pipa"
    $table->string('label', 128);       // "Diameter Pipa"
    $table->string('tipe_field', 32);   // text / number / date / boolean / select / textarea
    $table->jsonb('opsi')->nullable();
    $table->boolean('required')->default(false);
    $table->jsonb('validasi')->nullable();
    $table->integer('urutan')->default(0);
    $table->timestamps();

    $table->unique(['jenis_workorder_id', 'nama_field']);
});
```

### 12b. Tambah kolom `workorder.form_values`
```php
Schema::table('workorder', function (Blueprint $table) {
    $table->jsonb('form_values')->nullable()->after('latitude');
});
```

### 12c. Backfill data EAV → JSONB
```php
// Untuk tiap workorder yang punya progress_workorder tipe SELESAI:
//   ambil semua detail_progress.value yang terkait → rekonstruksi JSON
//   merge by detail_form.nama_field → simpan ke workorder.form_values

$workorders = DB::table('workorder')->pluck('id');
foreach ($workorders as $woId) {
    $values = DB::table('detail_progress as dp')
        ->join('progress_workorder as pw', 'pw.id', 'dp.progress_workorder_id')
        ->join('detail_form as df', 'df.id', 'dp.detail_form_id')
        ->where('pw.workorder_id', $woId)
        ->pluck('dp.value', 'df.nama_field')
        ->toArray();

    if (!empty($values)) {
        DB::table('workorder')->where('id', $woId)->update([
            'form_values' => json_encode($values),
        ]);
    }
}
```

### 12d. Migrasi `detail_form` → `form_template`
```php
$rows = DB::table('detail_form')->get();
foreach ($rows as $row) {
    DB::table('form_template')->updateOrInsert(
        ['jenis_workorder_id' => $row->jenis_workorder_id, 'nama_field' => $row->nama_field],
        [
            'label'      => $row->label ?? $row->nama_field,
            'tipe_field' => $row->tipe_field ?? 'text',
            'required'   => $row->required ?? false,
            'urutan'     => $row->order ?? 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]
    );
}
```

### 12e. Drop tabel EAV lama
```php
Schema::dropIfExists('detail_progress');
Schema::dropIfExists('detail_form');
// Note: jangan lupa hapus model DetailForm.php & DetailProgress.php + semua referensinya
```

**Service refactor**

```php
// app/Services/ProgressWorkorderService.php
public function createInitialProgress(int $workOrderId): void
{
    $tipeMulai   = TipeProgress::where('kode', 'MULAI')->value('id');
    $tipeSelesai = TipeProgress::where('kode', 'SELESAI')->value('id');
    $statusDraft = Status::where('kode', 'DRAFT')->value('id');

    ProgressWorkorder::create([
        'workorder_id'     => $workOrderId,
        'tipe_progress_id' => $tipeMulai,
        'status_id'        => $statusDraft,
        'order'            => 0,
    ]);
    ProgressWorkorder::create([
        'workorder_id'     => $workOrderId,
        'tipe_progress_id' => $tipeSelesai,
        'status_id'        => $statusDraft,
        'order'            => 1,
    ]);
    // HAPUS loop insert DetailProgress per field — sekarang form_values null sampai Staff isi.
}

// app/Services/WorkorderSubmissionService.php (baru)
public function submitSelesai(int $progressId, array $formValues, User $submitter): ProgressWorkorder
{
    $progress = ProgressWorkorder::with(['workorder.jenisWorkorder.formTemplates'])->findOrFail($progressId);

    // Validasi form_values terhadap form_template
    $this->validateAgainstTemplate($formValues, $progress->workorder->jenisWorkorder->formTemplates);

    DB::transaction(function () use ($progress, $formValues, $submitter) {
        $progress->workorder->update(['form_values' => $formValues]);
        $progress->update([
            'status_id'            => Status::where('kode', 'SUBMITTED')->value('id'),
            'waktu_submit'         => now(),
            'submitted_by_user_id' => $submitter->id,
        ]);
        $progress->workorder->update([
            'status_id' => Status::where('kode', 'MENUNGGU_VERIFIKASI_SPV')->value('id'),
        ]);
    });

    return $progress->fresh();
}

private function validateAgainstTemplate(array $values, $templates): void
{
    $templateFields = $templates->pluck('nama_field')->toArray();
    $unknownFields = array_diff(array_keys($values), $templateFields);
    if (!empty($unknownFields)) {
        throw ValidationException::withMessages([
            'form_values' => ['Field tidak dikenali: ' . implode(', ', $unknownFields)],
        ]);
    }

    // Cek required
    foreach ($templates as $tpl) {
        if ($tpl->required && empty($values[$tpl->nama_field])) {
            throw ValidationException::withMessages([
                "form_values.{$tpl->nama_field}" => ['Field wajib diisi.'],
            ]);
        }
    }
}
```

**Acceptance Criteria**
- [ ] Tabel `form_template` terbuat + index unique.
- [ ] `workorder.form_values` nullable JSONB ada.
- [ ] Backfill: semua WO historis punya `form_values` tidak null (kalau ada detail_progress).
- [ ] Tabel `detail_form` dan `detail_progress` di-drop.
- [ ] Model `DetailForm` & `DetailProgress` di-hapus + semua referensinya (service, controller).
- [ ] `GET /api/v1/workorder/{id}` tidak butuh JOIN ke `detail_progress` lagi — cukup pilih `form_values` dari workorder.
- [ ] `POST /api/v1/progress-workorder/{id}/submit` menerima `form_values` array, validasi via `form_template`.

**Risiko & strategi rollback**
- Backup DB sebelum migration.
- Kalau di prod → pisah jadi 3 PR: (1) tambah `form_template` + `form_values` (dual-read), (2) backfill, (3) drop EAV.
- Untuk TA (dev-only) cukup 1 PR.

**Files terdampak**
- `database/migrations/*_create_form_template_table.php` (baru)
- `database/migrations/*_add_form_values_to_workorder_table.php` (baru)
- `database/migrations/*_migrate_detail_form_to_form_template.php` (baru, data migration)
- `database/migrations/*_drop_detail_form_and_detail_progress_tables.php` (baru)
- `app/Models/FormTemplate.php` (baru)
- `app/Models/JenisWorkorder.php` (tambah relasi `formTemplates()`)
- `app/Models/Workorder.php` (cast `form_values` → `array`)
- `app/Services/ProgressWorkorderService.php`
- `app/Services/WorkorderSubmissionService.php` (baru)
- Hapus: `app/Models/DetailForm.php`, `app/Models/DetailProgress.php`

---

## TKT-13 · Workorder: kolom baru untuk Manager layer & Pengaduan

**Why**
Keputusan Q5 (Manager assign SPV + approve), Q7 (pengaduan_id nullable), Q11 (workorder.departemen_id wajib untuk policy).

**What**

### Migration
```php
Schema::table('workorder', function (Blueprint $table) {
    $table->foreignId('created_by_user_id')
          ->nullable()
          ->after('id')
          ->constrained('users');

    $table->foreignId('departemen_id')
          ->nullable()                       // nullable dulu, backfill, baru NOT NULL
          ->after('tipe_workorder_id')
          ->constrained('m_departemen');

    $table->foreignId('manager_id')
          ->nullable()
          ->after('pic_id')
          ->constrained('users');

    $table->foreignId('approved_by_user_id')
          ->nullable()
          ->after('manager_id')
          ->constrained('users');

    $table->dateTime('approved_at')->nullable()->after('approved_by_user_id');
    $table->text('approval_notes')->nullable()->after('approved_at');

    $table->foreignId('pengaduan_id')
          ->nullable()
          ->after('approval_notes')
          ->constrained('pengaduan');
});
```

### Backfill `departemen_id` untuk row lama
Strategi: ambil dari `pic.pegawai.departemen_id` (SPV). Setelah backfill, `NOT NULL`.

```php
DB::statement("
    UPDATE workorder w
    SET departemen_id = p.departemen_id
    FROM users u
    JOIN m_pegawai p ON p.id = u.pegawai_id
    WHERE u.id = w.pic_id
");

Schema::table('workorder', function (Blueprint $table) {
    $table->foreignId('departemen_id')->nullable(false)->change();
});
```

### Model
```php
// app/Models/Workorder.php (tambahan)
public function departemen()
{
    return $this->belongsTo(Departemen::class, 'departemen_id');
}

public function manager()
{
    return $this->belongsTo(User::class, 'manager_id')->with(['pegawai:id,nama,nip']);
}

public function approvedBy()
{
    return $this->belongsTo(User::class, 'approved_by_user_id')->with(['pegawai:id,nama,nip']);
}

public function createdBy()
{
    return $this->belongsTo(User::class, 'created_by_user_id')->with(['pegawai:id,nama,nip']);
}

public function pengaduan()
{
    return $this->belongsTo(Pengaduan::class, 'pengaduan_id');
}

protected $casts = [
    'latitude'     => 'float',
    'longitude'    => 'float',
    'location_id'  => 'integer',
    'form_values'  => 'array',   // TKT-12
    'approved_at'  => 'datetime', // TKT-13
];
```

### Service — update createWorkorders untuk simpan created_by + departemen_id
```php
$workorder = Workorder::create([
    // ...field lama...
    'created_by_user_id' => auth()->id(),
    'departemen_id'      => $data['departemen_id']
        ?? auth()->user()->pegawai?->departemen_id,  // default dari admin bagian
    'pengaduan_id'       => $data['pengaduan_id'] ?? null,
    'manager_id'         => null,   // diisi saat Manager assign
]);
```

**Acceptance Criteria**
- [ ] `workorder` punya 7 kolom baru (lihat migration).
- [ ] `departemen_id` backfilled & NOT NULL.
- [ ] Relasi model ter-add.
- [ ] Endpoint `POST /api/v1/workorder` menyimpan `created_by_user_id`, `departemen_id`, opsional `pengaduan_id`.

**Files terdampak**
- `database/migrations/*_add_manager_approval_to_workorder_table.php` (baru)
- `database/migrations/*_backfill_departemen_id_in_workorder.php` (baru)
- `app/Models/Workorder.php`
- `app/Services/WorkorderService.php`

---

## TKT-14 · `progress_workorder`: kolom review + append pattern untuk Revisi/Tolak

**Why**
Keputusan Q12 — Revisi/Tolak **append**, tidak hapus. Butuh kolom review + service method khusus.

**What**

### Migration
```php
Schema::table('progress_workorder', function (Blueprint $table) {
    $table->foreignId('reviewed_by_user_id')
          ->nullable()
          ->after('submitted_by_user_id')
          ->constrained('users');

    $table->dateTime('reviewed_at')->nullable()->after('reviewed_by_user_id');
    $table->text('alasan_penolakan')->nullable()->after('reviewed_at');
    $table->jsonb('field_to_revise')->nullable()->after('alasan_penolakan');
});
```

### Model
```php
// app/Models/ProgressWorkorder.php (tambahan)
public function reviewer()
{
    return $this->belongsTo(User::class, 'reviewed_by_user_id')->with(['pegawai:id,nama,nip']);
}

protected $casts = [
    'field_to_revise' => 'array',
    'reviewed_at'     => 'datetime',
    'waktu_submit'    => 'datetime',
];
```

### Dokumentasi progress: tambah `jenis`
```php
Schema::table('dokumentasi_progress', function (Blueprint $table) {
    $table->string('jenis', 32)->default('HASIL_KERJA')->after('url');
    // HASIL_KERJA | LAMPIRAN_REVISI
});
```

### Service — `WorkorderReviewService` (baru)
```php
namespace App\Services;

use App\Models\ProgressWorkorder;
use App\Models\Status;
use App\Models\TipeProgress;
use App\Models\User;
use App\Models\WorkorderAction;
use App\Models\MasterAction;
use Illuminate\Support\Facades\DB;

class WorkorderReviewService
{
    /**
     * SPV menerima hasil Staff → lanjut ke Manager.
     */
    public function terima(int $progressId, User $spv, ?string $catatan = null): ProgressWorkorder
    {
        return DB::transaction(function () use ($progressId, $spv, $catatan) {
            $progress = ProgressWorkorder::with('workorder')->findOrFail($progressId);

            $progress->update([
                'status_id'           => Status::where('kode', 'VERIFIED')->value('id'),
                'reviewed_by_user_id' => $spv->id,
                'reviewed_at'         => now(),
            ]);

            $progress->workorder->update([
                'status_id' => Status::where('kode', 'MENUNGGU_APPROVAL_MANAGER')->value('id'),
            ]);

            WorkorderAction::create([
                'workorder_id'  => $progress->workorder_id,
                'action_id'     => MasterAction::where('kode', 'VERIFIKASI_SPV')->value('id'),
                'actor_id'      => $spv->id,
                'keterangan'    => $catatan ?? 'Diverifikasi SPV',
                'waktu_mulai'   => now(),
            ]);

            return $progress->fresh();
        });
    }

    /**
     * SPV request revisi — append row REVISI, row SELESAI lama ditandai REVISI_REQUESTED.
     * Staff cukup lengkapi field yang diminta (Q12).
     */
    public function revisi(int $progressId, User $spv, string $alasan, array $fieldToRevise): ProgressWorkorder
    {
        return DB::transaction(function () use ($progressId, $spv, $alasan, $fieldToRevise) {
            $progressSelesai = ProgressWorkorder::with('workorder')->findOrFail($progressId);

            $progressSelesai->update([
                'status_id'           => Status::where('kode', 'REVISI_REQUESTED')->value('id'),
                'reviewed_by_user_id' => $spv->id,
                'reviewed_at'         => now(),
                'alasan_penolakan'    => $alasan,
                'field_to_revise'     => $fieldToRevise,
            ]);

            $maxOrder = ProgressWorkorder::where('workorder_id', $progressSelesai->workorder_id)->max('order');

            $revisiProgress = ProgressWorkorder::create([
                'workorder_id'     => $progressSelesai->workorder_id,
                'tipe_progress_id' => TipeProgress::where('kode', 'REVISI')->value('id'),
                'status_id'        => Status::where('kode', 'DRAFT')->value('id'),
                'order'            => $maxOrder + 1,
                'hasil_pengerjaan' => $alasan,
                'submitted_by_user_id' => $spv->id,
                'waktu_submit'     => now(),
            ]);

            $progressSelesai->workorder->update([
                'status_id' => Status::where('kode', 'DALAM_PENGERJAAN')->value('id'),
            ]);

            WorkorderAction::create([
                'workorder_id'  => $progressSelesai->workorder_id,
                'action_id'     => MasterAction::where('kode', 'REVISI')->value('id'),
                'actor_id'      => $spv->id,
                'keterangan'    => $alasan,
                'waktu_mulai'   => now(),
            ]);

            return $revisiProgress;
        });
    }

    /**
     * SPV tolak final — append row DITOLAK, row SELESAI lama ditandai DITOLAK_SPV, WO final.
     */
    public function tolak(int $progressId, User $spv, string $alasan): ProgressWorkorder
    {
        return DB::transaction(function () use ($progressId, $spv, $alasan) {
            $progressSelesai = ProgressWorkorder::with('workorder')->findOrFail($progressId);

            $progressSelesai->update([
                'status_id'           => Status::where('kode', 'DITOLAK_SPV')->value('id'),
                'reviewed_by_user_id' => $spv->id,
                'reviewed_at'         => now(),
                'alasan_penolakan'    => $alasan,
            ]);

            $maxOrder = ProgressWorkorder::where('workorder_id', $progressSelesai->workorder_id)->max('order');

            $tolakProgress = ProgressWorkorder::create([
                'workorder_id'     => $progressSelesai->workorder_id,
                'tipe_progress_id' => TipeProgress::where('kode', 'DITOLAK')->value('id'),
                'status_id'        => Status::where('kode', 'DITOLAK_SPV')->value('id'),
                'order'            => $maxOrder + 1,
                'hasil_pengerjaan' => $alasan,
                'submitted_by_user_id' => $spv->id,
                'waktu_submit'     => now(),
            ]);

            $progressSelesai->workorder->update([
                'status_id' => Status::where('kode', 'DITOLAK_SPV')->value('id'),
            ]);

            WorkorderAction::create([
                'workorder_id'  => $progressSelesai->workorder_id,
                'action_id'     => MasterAction::where('kode', 'REJECT')->value('id'),
                'actor_id'      => $spv->id,
                'keterangan'    => $alasan,
                'waktu_mulai'   => now(),
            ]);

            return $tolakProgress;
        });
    }
}
```

### Controller baru
```php
// app/Http/Controllers/WorkorderReviewController.php (baru)
public function terima(Request $r, int $progressId)     { ... }
public function revisi(Request $r, int $progressId)     { ... }   // validate: alasan wajib, field_to_revise array
public function tolak(Request $r, int $progressId)      { ... }   // validate: alasan wajib
```

### Route tambahan
```php
Route::prefix('api/v1/progress-workorder/{progressId}')->middleware('auth:sanctum')->group(function () {
    Route::post('/terima', [WorkorderReviewController::class, 'terima']);
    Route::post('/revisi', [WorkorderReviewController::class, 'revisi']);
    Route::post('/tolak',  [WorkorderReviewController::class, 'tolak']);
});
```

**Acceptance Criteria**
- [ ] Kolom baru ter-add di `progress_workorder` + `dokumentasi_progress`.
- [ ] `WorkorderReviewService` bekerja untuk 3 skenario: Terima / Revisi / Tolak.
- [ ] Setiap aksi review → `workorder_action` tercatat (audit trail).
- [ ] Tidak ada `DELETE` pada `progress_workorder` di service apapun.
- [ ] Unit test 3 method di service (minimal happy path + 1 sad path per method).

**Files terdampak**
- `database/migrations/*_add_review_fields_to_progress_workorder.php` (baru)
- `database/migrations/*_add_jenis_to_dokumentasi_progress.php` (baru)
- `app/Models/ProgressWorkorder.php`
- `app/Services/WorkorderReviewService.php` (baru)
- `app/Http/Controllers/WorkorderReviewController.php` (baru)
- `routes/api.php`

---

## TKT-15 · Tabel `laporan_workorder` + event listener + generate PDF

**Why**
Q13–Q14 — laporan sebagai **output resmi** (1 WO = 1 laporan), auto-generate saat Manager approve, bisa dicetak PDF untuk internal.

**What**

### Migration
```php
Schema::create('laporan_workorder', function (Blueprint $table) {
    $table->id();
    $table->foreignId('workorder_id')->unique()->constrained('workorder');
    $table->string('nomor_laporan', 32)->unique();
    $table->dateTime('tanggal_terbit');
    $table->text('ringkasan_pekerjaan')->nullable();
    $table->jsonb('hasil_akhir_snapshot')->nullable();
    $table->jsonb('petugas_snapshot')->nullable();
    $table->text('catatan_spv')->nullable();
    $table->text('catatan_manager')->nullable();
    $table->string('pdf_url', 512)->nullable();
    $table->foreignId('issued_by_user_id')->constrained('users');
    $table->foreignId('approved_by_user_id')->constrained('users');
    $table->dateTime('approved_at');
    $table->timestamps();

    $table->index(['tanggal_terbit']);
});
```

### Model
```php
// app/Models/LaporanWorkorder.php (baru)
class LaporanWorkorder extends Model
{
    protected $table = 'laporan_workorder';
    protected $guarded = [];
    protected $casts = [
        'tanggal_terbit'        => 'datetime',
        'approved_at'           => 'datetime',
        'hasil_akhir_snapshot'  => 'array',
        'petugas_snapshot'      => 'array',
    ];

    public function workorder()    { return $this->belongsTo(Workorder::class, 'workorder_id'); }
    public function issuedBy()     { return $this->belongsTo(User::class, 'issued_by_user_id')->with(['pegawai:id,nama,nip']); }
    public function approvedBy()   { return $this->belongsTo(User::class, 'approved_by_user_id')->with(['pegawai:id,nama,nip']); }
}
```

### Service — nomor laporan
```php
// app/Services/NomorLaporanGenerator.php (baru)
class NomorLaporanGenerator
{
    public function generate(?int $year = null): string
    {
        $year = $year ?? (int) now()->format('Y');
        $last = LaporanWorkorder::whereYear('tanggal_terbit', $year)
            ->orderByDesc('id')
            ->value('nomor_laporan');
        $seq  = $last ? ((int) substr($last, -4)) + 1 : 1;
        return sprintf('LAP-WO-%d-%04d', $year, $seq);
    }
}
```

### Event + Listener
```php
// app/Events/WorkorderApproved.php
class WorkorderApproved
{
    public function __construct(public Workorder $workorder, public User $approver, public ?string $catatan = null) {}
}

// app/Listeners/IssueWorkorderReport.php
class IssueWorkorderReport
{
    public function handle(WorkorderApproved $event): void
    {
        $wo = $event->workorder->load(['petugasList.pegawai', 'progressWorkorder']);
        $spvProgress = $wo->progressWorkorder()
            ->whereHas('status', fn($q) => $q->where('kode', 'VERIFIED'))
            ->latest()
            ->first();

        $laporan = LaporanWorkorder::create([
            'workorder_id'          => $wo->id,
            'nomor_laporan'         => app(NomorLaporanGenerator::class)->generate(),
            'tanggal_terbit'        => now(),
            'ringkasan_pekerjaan'   => $spvProgress?->hasil_pengerjaan,
            'hasil_akhir_snapshot'  => $wo->form_values,
            'petugas_snapshot'      => $wo->petugasList->map(fn($u) => [
                'user_id' => $u->id, 'nama' => $u->pegawai?->nama, 'nip' => $u->pegawai?->nip,
            ])->all(),
            'catatan_manager'       => $event->catatan,
            'issued_by_user_id'     => $wo->pic_id,
            'approved_by_user_id'   => $event->approver->id,
            'approved_at'           => now(),
        ]);

        // Dispatch PDF generation as Job (async)
        dispatch(new GenerateLaporanPdfJob($laporan->id));
    }
}
```

### Job — render PDF
```php
// app/Jobs/GenerateLaporanPdfJob.php
use Barryvdh\DomPDF\Facade\Pdf;

class GenerateLaporanPdfJob implements ShouldQueue
{
    public function __construct(public int $laporanId) {}

    public function handle(): void
    {
        $laporan = LaporanWorkorder::with(['workorder.jenisWorkorder', 'issuedBy.pegawai', 'approvedBy.pegawai'])
            ->findOrFail($this->laporanId);

        $pdf = Pdf::loadView('pdf.laporan-workorder', compact('laporan'));
        $filename = "{$laporan->nomor_laporan}.pdf";
        $path = "laporan/{$filename}";

        Storage::disk('public')->put($path, $pdf->output());
        $url = Storage::disk('public')->url($path);

        $laporan->update(['pdf_url' => $url]);
    }
}
```

### Trigger di `WorkorderApprovalService` (baru)
```php
// app/Services/WorkorderApprovalService.php
class WorkorderApprovalService
{
    public function approve(int $workorderId, User $manager, ?string $catatan = null): Workorder
    {
        return DB::transaction(function () use ($workorderId, $manager, $catatan) {
            $wo = Workorder::findOrFail($workorderId);

            // Validasi role
            abort_unless($manager->role_id === 2, 403, 'Hanya Manager yang bisa approve');
            // Validasi departemen (Q11)
            abort_unless(
                $wo->departemen_id === $manager->pegawai?->departemen_id,
                403,
                'Manager hanya bisa approve WO di departemennya'
            );

            $wo->update([
                'status_id'           => Status::where('kode', 'SELESAI')->value('id'),
                'approved_by_user_id' => $manager->id,
                'approved_at'         => now(),
                'approval_notes'      => $catatan,
            ]);

            WorkorderAction::create([
                'workorder_id' => $wo->id,
                'action_id'    => MasterAction::where('kode', 'APPROVE')->value('id'),
                'actor_id'     => $manager->id,
                'keterangan'   => $catatan ?? 'Disetujui Manager',
                'waktu_mulai'  => now(),
            ]);

            event(new WorkorderApproved($wo->fresh(), $manager, $catatan));

            return $wo->fresh();
        });
    }

    public function reject(int $workorderId, User $manager, string $alasan): Workorder
    {
        // Mirip approve tapi status_id = DITOLAK_MANAGER dan kirim ke in-progress lagi
        // Tidak trigger WorkorderApproved event
    }
}
```

### View PDF (Blade)
File `resources/views/pdf/laporan-workorder.blade.php` — template sederhana dengan header PDAM + tabel info WO + foto dokumentasi. Untuk TA, template 1 halaman cukup.

### Route
```php
Route::prefix('api/v1/workorder/{id}')->middleware('auth:sanctum')->group(function () {
    Route::post('/approve', [WorkorderApprovalController::class, 'approve']);
    Route::post('/reject',  [WorkorderApprovalController::class, 'reject']);
});

Route::get('/api/v1/laporan-workorder/{id}', [LaporanWorkorderController::class, 'show'])->middleware('auth:sanctum');
Route::get('/api/v1/laporan-workorder/{id}/pdf', [LaporanWorkorderController::class, 'downloadPdf'])->middleware('auth:sanctum');
```

### Composer dependency
```bash
composer require barryvdh/laravel-dompdf
```

**Acceptance Criteria**
- [ ] Tabel `laporan_workorder` terbuat + unique constraint (`workorder_id`, `nomor_laporan`).
- [ ] Event `WorkorderApproved` dispatch saat Manager approve.
- [ ] Listener auto-insert row laporan + dispatch PDF job.
- [ ] Endpoint `GET /api/v1/laporan-workorder/{id}` return detail laporan.
- [ ] Endpoint `GET /api/v1/laporan-workorder/{id}/pdf` return binary PDF atau redirect ke URL Cloudinary/local.
- [ ] Nomor laporan format `LAP-WO-YYYY-NNNN`, unique, deterministic.
- [ ] Job PDF di-queue (tidak blocking HTTP request).

**Files terdampak** (banyak, semua baru)
- `database/migrations/*_create_laporan_workorder_table.php`
- `app/Models/LaporanWorkorder.php`
- `app/Services/NomorLaporanGenerator.php`
- `app/Services/WorkorderApprovalService.php`
- `app/Events/WorkorderApproved.php`
- `app/Listeners/IssueWorkorderReport.php`
- `app/Jobs/GenerateLaporanPdfJob.php`
- `app/Http/Controllers/WorkorderApprovalController.php`
- `app/Http/Controllers/LaporanWorkorderController.php`
- `resources/views/pdf/laporan-workorder.blade.php`
- `app/Providers/EventServiceProvider.php` (register listener)
- `routes/api.php`
- `composer.json` (tambah `barryvdh/laravel-dompdf`)

---

## TKT-16 · Policy Manager–Departemen (Q11)

**Why**
Validasi "Manager hanya bisa assign/approve WO di departemennya" wajib di-enforce di backend, bukan hanya di UI. Pakai `Gate`/`Policy` Laravel.

**What**

### Policy
```php
// app/Policies/WorkorderPolicy.php
namespace App\Policies;

use App\Models\User;
use App\Models\Workorder;

class WorkorderPolicy
{
    /** Super Admin bisa semua */
    public function before(User $user): ?bool
    {
        return $user->role_id === 1 ? true : null;
    }

    /** Manager assign SPV — hanya WO di departemennya */
    public function assignSpv(User $user, Workorder $workorder): bool
    {
        return $user->role_id === 2
            && $workorder->departemen_id === $user->pegawai?->departemen_id;
    }

    /** Manager approve / reject — hanya WO di departemennya */
    public function approveOrReject(User $user, Workorder $workorder): bool
    {
        return $user->role_id === 2
            && $workorder->departemen_id === $user->pegawai?->departemen_id;
    }

    /** SPV assign Staff — hanya WO dimana dia jadi pic */
    public function assignStaff(User $user, Workorder $workorder): bool
    {
        return $user->id === $workorder->pic_id;
    }

    /** SPV review hasil Staff — hanya WO dimana dia jadi pic */
    public function review(User $user, Workorder $workorder): bool
    {
        return $user->id === $workorder->pic_id;
    }

    /** Staff submit progres — hanya WO dimana dia di workorder_petugas */
    public function submitProgress(User $user, Workorder $workorder): bool
    {
        return $workorder->petugasList()->where('users.id', $user->id)->exists();
    }
}
```

### Register di `AuthServiceProvider`
```php
protected $policies = [
    Workorder::class => WorkorderPolicy::class,
];
```

### Gunakan di Controller
```php
// contoh di WorkorderApprovalController
public function approve(Request $r, int $id)
{
    $wo = Workorder::findOrFail($id);
    $this->authorize('approveOrReject', $wo);    // throw 403 kalau gagal

    $catatan = $r->input('catatan');
    $result = (new WorkorderApprovalService())->approve($id, $r->user(), $catatan);
    return response()->json(['message' => 'Approved', 'data' => $result]);
}
```

**Acceptance Criteria**
- [ ] `WorkorderPolicy` ter-register di `AuthServiceProvider`.
- [ ] Manager Operasional mencoba approve WO Pelayanan → HTTP 403.
- [ ] Manager Operasional approve WO Operasional → HTTP 200.
- [ ] SPV lain coba review hasil Staff di WO yang bukan miliknya → HTTP 403.
- [ ] Staff coba submit progres di WO yang dia tidak di-assign → HTTP 403.

**Files terdampak**
- `app/Policies/WorkorderPolicy.php` (baru)
- `app/Providers/AuthServiceProvider.php`
- Semua controller yang terkait aksi WO.

---

# Penutup & catatan koordinasi

1. **Urutan deploy ticket lanjutan**: TKT-08 → TKT-09 → TKT-10 → TKT-11 → TKT-12 → TKT-13 → TKT-14 → TKT-15 → TKT-16. TKT-12 paling beresiko karena drop tabel — rencanakan backup.

2. **Koordinasi dengan FE Web (Next.js)**: TKT-07 mengganti `workorder.petugas` (objek) → `workorder.petugas_list` (array). Ini breaking. Sebelum merge TKT-07, sinkron dulu sama Geo.

3. **TKT-12 breaking untuk kedua FE**: response `GET /api/v1/workorder/{id}` akan punya `form_values` (object) sebagai ganti `progress_workorder[].detail_progress[]`. Notify Flutter + Next.js **sebelum** PR di-merge.

4. **Frontend Flutter untuk Review SPV (TKT-14)**: butuh 3 endpoint baru (`/terima`, `/revisi`, `/tolak`). Lihat mockup Figma — modal `Revisi` dan `Tolak` sudah didesain, tinggal wire ke API.

5. **Seeder**: setelah TKT-09, TKT-10, TKT-11 merged, update `DatabaseSeeder` untuk insert master data baru + pengaduan mock + form_template. Pastikan urutan seeder:
   ```
   Role → Jabatan → Departemen (2) → Status → TipeProgress → MasterAction → JenisPengaduan → MasterLocation
   → Pegawai (Admin, Manager x2 per departemen, SPV x4, Staff x8) → User
   → JenisWorkorder → FormTemplate → Pengaduan (mock) → Workorder (demo)
   ```

6. **Testing checklist sebelum sidang**:
   - Login semua 4 role → dashboard sesuai role
   - Admin bikin WO dari pengaduan → Manager receive di dashboard
   - Manager assign SPV → SPV receive
   - SPV assign multi-Staff → Staff receive di mobile
   - Staff Mulai → isi form → submit Selesai
   - SPV Terima → WO pindah ke Manager
   - SPV Revisi → row baru REVISI, Staff kerjakan lagi
   - SPV Tolak → WO final DITOLAK_SPV
   - Manager Approve → laporan auto-generate + nomor `LAP-WO-YYYY-NNNN`
   - Download PDF laporan berhasil

Bilang aja kalau mau aku breakdown salah satu ticket lebih detail (mis. test plan), tambah mockup API response, atau generate file seeder konkret untuk `form_template` per jenis WO.

