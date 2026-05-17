<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class RestructureWorkorderAndAssignment extends Migration
{
    public function up()
    {
        // ─── 1. Drop kolom legacy dari workorder ────────────────────────
        Schema::table('workorder', function (Blueprint $table) {
            // Drop FK dulu, lalu kolom
            if (Schema::hasColumn('workorder', 'location_id')) {
                $table->dropForeign(['location_id']);
                $table->dropColumn('location_id');
            }
            if (Schema::hasColumn('workorder', 'jenis_lokasi_id')) {
                $table->dropForeign(['jenis_lokasi_id']);
                $table->dropColumn('jenis_lokasi_id');
            }
            if (Schema::hasColumn('workorder', 'tipe_workorder_id')) {
                $table->dropForeign(['tipe_workorder_id']);
                $table->dropColumn('tipe_workorder_id');
            }
            if (Schema::hasColumn('workorder', 'latitude')) {
                $table->dropColumn('latitude');
            }
            if (Schema::hasColumn('workorder', 'longitude')) {
                $table->dropColumn('longitude');
            }

            if (Schema::hasColumn('workorder', 'estimasi_durasi')) {
                $table->dropColumn('estimasi_durasi');
            }
            if (Schema::hasColumn('workorder', 'unit_waktu')) {
                $table->dropColumn('unit_waktu');
            }
            if (Schema::hasColumn('workorder', 'estimasi_selesai')) {
                $table->dropColumn('estimasi_selesai');
            }
        });

        if (Schema::hasColumn('workorder_assignment', 'catatan_spv')
            && ! Schema::hasColumn('workorder_assignment', 'deskripsi')) {
            DB::statement('ALTER TABLE workorder_assignment RENAME COLUMN catatan_spv TO deskripsi');
        }

        // Jika catatan_spv tidak ada dan deskripsi juga belum ada, buat baru
        if (! Schema::hasColumn('workorder_assignment', 'deskripsi')) {
            Schema::table('workorder_assignment', function (Blueprint $table) {
                $table->text('deskripsi')->nullable()->after('assigned_at');
            });
        }

        // Pisah schema call setelah rename (Laravel/Doctrine limitation)
        Schema::table('workorder_assignment', function (Blueprint $table) {
            if (! Schema::hasColumn('workorder_assignment', 'tipe_workorder')) {
                $table->text('tipe_workorder')->nullable()->after('deskripsi');
            }
            if (! Schema::hasColumn('workorder_assignment', 'tanggal_mulai')) {
                $table->timestamp('tanggal_mulai')->nullable()->after('tipe_workorder');
            }
            if (! Schema::hasColumn('workorder_assignment', 'tanggal_selesai')) {
                $table->timestamp('tanggal_selesai')->nullable()->after('tanggal_mulai');
            }
            if (! Schema::hasColumn('workorder_assignment', 'estimasi_selesai')) {
                $table->timestamp('estimasi_selesai')->nullable()->after('tanggal_selesai');
            }
            if (! Schema::hasColumn('workorder_assignment', 'latitude')) {
                $table->decimal('latitude', 10, 7)->nullable()->after('estimasi_selesai');
            }
            if (! Schema::hasColumn('workorder_assignment', 'longitude')) {
                $table->decimal('longitude', 10, 7)->nullable()->after('latitude');
            }
            if (! Schema::hasColumn('workorder_assignment', 'accuracy')) {
                $table->float('accuracy')->nullable()->after('longitude');
            }
            if (! Schema::hasColumn('workorder_assignment', 'location_id')) {
                $table->foreignId('location_id')->nullable()->after('accuracy')
                    ->constrained('m_location');
            }
        });
    }

    public function down()
    {
        // ─── Rollback: hapus kolom baru dari workorder_assignment ───────
        Schema::table('workorder_assignment', function (Blueprint $table) {
            if (Schema::hasColumn('workorder_assignment', 'location_id')) {
                $table->dropForeign(['location_id']);
                $table->dropColumn('location_id');
            }
            $dropCols = ['accuracy', 'longitude', 'latitude', 'estimasi_selesai',
                         'tanggal_selesai', 'tanggal_mulai', 'tipe_workorder'];
            foreach ($dropCols as $col) {
                if (Schema::hasColumn('workorder_assignment', $col)) {
                    $table->dropColumn($col);
                }
            }
        });

        // Rename deskripsi kembali ke catatan_spv
        if (Schema::hasColumn('workorder_assignment', 'deskripsi')
            && ! Schema::hasColumn('workorder_assignment', 'catatan_spv')) {
            DB::statement('ALTER TABLE workorder_assignment RENAME COLUMN deskripsi TO catatan_spv');
        }

        // ─── Rollback: kembalikan kolom ke workorder ────────────────────
        Schema::table('workorder', function (Blueprint $table) {
            if (! Schema::hasColumn('workorder', 'latitude')) {
                $table->decimal('latitude', 8, 6)->nullable();
            }
            if (! Schema::hasColumn('workorder', 'longitude')) {
                $table->decimal('longitude', 9, 6)->nullable();
            }
            if (! Schema::hasColumn('workorder', 'location_id')) {
                $table->foreignId('location_id')->nullable()->constrained('m_location');
            }
            if (! Schema::hasColumn('workorder', 'jenis_lokasi_id')) {
                $table->foreignId('jenis_lokasi_id')->nullable()->constrained('m_jenis_lokasi');
            }
            if (! Schema::hasColumn('workorder', 'tipe_workorder_id')) {
                $table->foreignId('tipe_workorder_id')->nullable()->constrained('m_tipe_workorder');
            }
            // Kembalikan kolom timeline
            if (! Schema::hasColumn('workorder', 'estimasi_durasi')) {
                $table->integer('estimasi_durasi')->nullable();
            }
            if (! Schema::hasColumn('workorder', 'unit_waktu')) {
                $table->string('unit_waktu')->nullable();
            }
            if (! Schema::hasColumn('workorder', 'estimasi_selesai')) {
                $table->datetime('estimasi_selesai')->nullable();
            }
        });
    }
}
