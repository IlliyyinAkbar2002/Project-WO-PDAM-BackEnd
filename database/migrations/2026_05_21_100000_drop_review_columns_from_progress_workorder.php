<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Pindahkan SELURUH kolom review dari `progress_workorder` ke `progress_detail`.
 *
 * Latar belakang:
 * - Kolom review (reviewed_by_user_id, reviewed_at, alasan_penolakan,
 *   field_to_revise) seharusnya hanya ada di `progress_detail` — 1 progres
 *   bisa punya banyak siklus review (initial + resubmit). Menyimpannya juga
 *   di `progress_workorder` membuat data terduplikasi.
 * - Saat tabel `progress_detail` dibuat (migration 2026_05_18_100000),
 *   kolom-kolom ini sengaja dipertahankan di `progress_workorder` untuk
 *   backward compatibility. Sekarang kita finalisasi pemindahan.
 *
 * Strategi:
 * 1. Backfill: untuk setiap baris di `progress_workorder` yang sudah pernah
 *    direview tapi belum punya catatan terminal (approved/rejected) di
 *    `progress_detail`, salin datanya ke `progress_detail` agar history tidak
 *    hilang.
 * 2. Drop: hapus 4 kolom review dari `progress_workorder`
 *    (reviewed_by_user_id, reviewed_at, alasan_penolakan, field_to_revise).
 *    Untuk reviewed_by_user_id pakai dropConstrainedForeignId agar FK
 *    constraint ikut terhapus.
 */
class DropReviewColumnsFromProgressWorkorder extends Migration
{
    public function up()
    {
        if (Schema::hasTable('progress_workorder') && Schema::hasTable('progress_detail')) {
            $this->backfillReviewDataToProgressDetail();
        }

        // reviewed_by_user_id punya FK ke users, jadi drop terpisah pakai
        // dropConstrainedForeignId() (hapus constraint + kolom sekaligus).
        if (Schema::hasColumn('progress_workorder', 'reviewed_by_user_id')) {
            Schema::table('progress_workorder', function (Blueprint $table) {
                $table->dropConstrainedForeignId('reviewed_by_user_id');
            });
        }

        Schema::table('progress_workorder', function (Blueprint $table) {
            $columns = [];
            if (Schema::hasColumn('progress_workorder', 'reviewed_at')) {
                $columns[] = 'reviewed_at';
            }
            if (Schema::hasColumn('progress_workorder', 'alasan_penolakan')) {
                $columns[] = 'alasan_penolakan';
            }
            if (Schema::hasColumn('progress_workorder', 'field_to_revise')) {
                $columns[] = 'field_to_revise';
            }

            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }

    public function down()
    {
        Schema::table('progress_workorder', function (Blueprint $table) {
            if (! Schema::hasColumn('progress_workorder', 'reviewed_by_user_id')) {
                $table->foreignId('reviewed_by_user_id')
                    ->nullable()
                    ->after('submitted_by_user_id')
                    ->constrained('users');
            }
            if (! Schema::hasColumn('progress_workorder', 'alasan_penolakan')) {
                $table->text('alasan_penolakan')->nullable()->after('order');
            }
            if (! Schema::hasColumn('progress_workorder', 'field_to_revise')) {
                $table->jsonb('field_to_revise')->nullable()->after('alasan_penolakan');
            }
            if (! Schema::hasColumn('progress_workorder', 'reviewed_at')) {
                $table->timestamp('reviewed_at')->nullable()->after('reviewed_by_user_id');
            }
        });
    }

    /**
     * Salin data review yang ada di progress_workorder ke progress_detail
     * agar tidak hilang saat kolom dihapus.
     *
     * Aturan:
     * - Hanya backfill baris yang BELUM punya catatan terminal
     *   (approved/rejected) di progress_detail.
     * - field_to_revise di progress_workorder bertipe jsonb (array), di
     *   progress_detail bertipe varchar (comma-separated). Konversi via
     *   json_decode + implode.
     * - Status diturunkan dari data yang tersedia:
     *     alasan_penolakan != null → 'rejected'
     *     reviewed_at != null      → 'approved'
     *     selain itu               → 'pending'
     */
    private function backfillReviewDataToProgressDetail(): void
    {
        $hasReviewedAt = Schema::hasColumn('progress_workorder', 'reviewed_at');
        $hasAlasan     = Schema::hasColumn('progress_workorder', 'alasan_penolakan');
        $hasField      = Schema::hasColumn('progress_workorder', 'field_to_revise');

        if (! $hasReviewedAt && ! $hasAlasan && ! $hasField) {
            return;
        }

        $select = ['id', 'reviewed_by_user_id'];
        if ($hasReviewedAt) {
            $select[] = 'reviewed_at';
        }
        if ($hasAlasan) {
            $select[] = 'alasan_penolakan';
        }
        if ($hasField) {
            $select[] = 'field_to_revise';
        }

        $query = DB::table('progress_workorder')->select($select);
        $query->where(function ($q) use ($hasReviewedAt, $hasAlasan, $hasField) {
            if ($hasReviewedAt) {
                $q->orWhereNotNull('reviewed_at');
            }
            if ($hasAlasan) {
                $q->orWhereNotNull('alasan_penolakan');
            }
            if ($hasField) {
                $q->orWhereNotNull('field_to_revise');
            }
        });

        foreach ($query->cursor() as $row) {
            $reviewedAt    = $hasReviewedAt ? ($row->reviewed_at ?? null) : null;
            $alasan        = $hasAlasan     ? ($row->alasan_penolakan ?? null) : null;
            $fieldToRevise = $hasField      ? ($row->field_to_revise ?? null) : null;

            // Skip kalau sudah ada catatan terminal di progress_detail
            $hasTerminal = DB::table('progress_detail')
                ->where('progress_workorder_id', $row->id)
                ->whereIn('status', ['approved', 'rejected'])
                ->exists();

            if ($hasTerminal) {
                continue;
            }

            $status = 'pending';
            if (! empty($alasan)) {
                $status = 'rejected';
            } elseif (! empty($reviewedAt)) {
                $status = 'approved';
            }

            // Konversi jsonb (array) → comma-separated string
            $fieldStr = null;
            if ($fieldToRevise !== null) {
                $decoded = json_decode((string) $fieldToRevise, true);
                $fieldStr = is_array($decoded)
                    ? implode(',', array_map('strval', $decoded))
                    : (string) $fieldToRevise;
            }

            $now = now();

            DB::table('progress_detail')->insert([
                'progress_workorder_id' => $row->id,
                'status'                => $status,
                'reviewed_by_user_id'   => $row->reviewed_by_user_id ?? null,
                'reviewed_at'           => $reviewedAt,
                'alasan_penolakan'      => $alasan,
                'field_to_revise'       => $fieldStr,
                'created_at'            => $reviewedAt ?? $now,
                'updated_at'            => $now,
            ]);
        }
    }
}
