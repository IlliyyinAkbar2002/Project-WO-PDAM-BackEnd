<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AlignSchemaWithErdPhysical extends Migration
{
    public function up()
    {
        $this->renameMasterKpiTable();
        $this->alterJenisWorkorder();
        $this->createJenisPengaduanTable();
        $this->createPengaduanTable();
        $this->alterWorkorderTable();
        $this->createWoCategoryTables();
        $this->alterProgressWorkorderTable();
        $this->alterDokumentasiProgressTable();
        $this->createLaporanWorkorderTable();
    }

    public function down()
    {
        Schema::dropIfExists('laporan_workorder');
        Schema::dropIfExists('wo_infrastruktur');
        Schema::dropIfExists('wo_jaringan');
        Schema::dropIfExists('wo_meter');
        Schema::dropIfExists('pengaduan');
        Schema::dropIfExists('m_jenis_pengaduan');
    }

    private function renameMasterKpiTable(): void
    {
        if (Schema::hasTable('master_kpi') && ! Schema::hasTable('m_kpi')) {
            Schema::rename('master_kpi', 'm_kpi');
        }

        if (! Schema::hasTable('m_kpi')) {
            return;
        }

        Schema::table('m_kpi', function (Blueprint $table) {
            if (! Schema::hasColumn('m_kpi', 'kode')) {
                $table->string('kode', 32)->nullable()->after('id');
            }
            if (! Schema::hasColumn('m_kpi', 'bobot')) {
                $table->decimal('bobot', 5, 2)->nullable()->after('nama');
            }
            if (! Schema::hasColumn('m_kpi', 'deskripsi')) {
                $table->text('deskripsi')->nullable()->after('bobot');
            }
        });

        DB::table('m_kpi')->whereNull('kode')->orderBy('id')->get()->each(function ($row) {
            $kode = strtoupper(preg_replace('/[^A-Z0-9]+/i', '_', $row->nama)) ?: 'KPI_' . $row->id;
            DB::table('m_kpi')->where('id', $row->id)->update(['kode' => $kode]);
        });

        if (! $this->hasIndex('m_kpi', 'm_kpi_kode_unique')) {
            Schema::table('m_kpi', function (Blueprint $table) {
                $table->unique('kode');
            });
        }
    }

    private function alterJenisWorkorder(): void
    {
        if (! Schema::hasTable('m_jenis_workorder')) {
            return;
        }

        Schema::table('m_jenis_workorder', function (Blueprint $table) {
            if (! Schema::hasColumn('m_jenis_workorder', 'kategori_form')) {
                $table->string('kategori_form', 32)->nullable()->after('nama');
            }
        });

        if (! $this->hasIndex('m_jenis_workorder', 'm_jenis_workorder_kategori_form_index')) {
            Schema::table('m_jenis_workorder', function (Blueprint $table) {
                $table->index('kategori_form');
            });
        }
    }

    private function createJenisPengaduanTable(): void
    {
        if (Schema::hasTable('m_jenis_pengaduan')) {
            return;
        }

        Schema::create('m_jenis_pengaduan', function (Blueprint $table) {
            $table->id();
            $table->string('kode', 32)->unique();
            $table->string('nama');
            $table->timestamps();
        });
    }

    private function createPengaduanTable(): void
    {
        if (Schema::hasTable('pengaduan')) {
            return;
        }

        Schema::create('pengaduan', function (Blueprint $table) {
            $table->id();
            $table->string('external_id')->nullable()->unique();
            $table->string('nomor_pelanggan')->nullable();
            $table->string('nama_pelapor')->nullable();
            $table->string('kontak_pelapor')->nullable();
            $table->string('alamat')->nullable();
            $table->text('deskripsi')->nullable();
            $table->timestamp('tanggal_lapor');
            $table->foreignId('jenis_pengaduan_id')->constrained('m_jenis_pengaduan');
            $table->foreignId('status_id')->constrained('m_status');
            $table->foreignId('departemen_id')->nullable()->constrained('m_departemen');
            $table->foreignId('duplicate_of_id')->nullable()->constrained('pengaduan')->nullOnDelete();
            $table->timestamp('fetched_at')->nullable();
            $table->jsonb('raw_payload')->nullable();
            $table->timestamps();
        });

        DB::statement('CREATE INDEX IF NOT EXISTS pengaduan_raw_payload_gin ON pengaduan USING GIN (raw_payload)');
    }

    private function alterWorkorderTable(): void
    {
        if (! Schema::hasTable('workorder')) {
            return;
        }

        if (Schema::hasColumn('workorder', 'judul_pekerjaan') && ! Schema::hasColumn('workorder', 'nama_workorder')) {
            DB::statement('ALTER TABLE workorder RENAME COLUMN judul_pekerjaan TO nama_workorder');
        }

        if (Schema::hasColumn('workorder', 'waktu_penugasan') && ! Schema::hasColumn('workorder', 'tanggal_mulai')) {
            DB::statement('ALTER TABLE workorder RENAME COLUMN waktu_penugasan TO tanggal_mulai');
        }

        if (Schema::hasColumn('workorder', 'pic_id') && ! Schema::hasColumn('workorder', 'assigned_to')) {
            DB::statement('ALTER TABLE workorder RENAME COLUMN pic_id TO assigned_to');
        }

        Schema::table('workorder', function (Blueprint $table) {
            if (! Schema::hasColumn('workorder', 'deskripsi')) {
                $table->text('deskripsi')->nullable()->after('nama_workorder');
            }
            if (! Schema::hasColumn('workorder', 'pengaduan_id')) {
                $table->foreignId('pengaduan_id')->nullable()->after('deskripsi')->constrained('pengaduan')->nullOnDelete();
            }
            if (! Schema::hasColumn('workorder', 'departemen_id')) {
                $table->foreignId('departemen_id')->nullable()->after('pengaduan_id')->constrained('m_departemen');
            }
            if (! Schema::hasColumn('workorder', 'lokasi')) {
                $table->string('lokasi')->nullable()->after('jenis_lokasi_id');
            }
            if (! Schema::hasColumn('workorder', 'prioritas')) {
                $table->string('prioritas', 32)->nullable()->after('longitude');
            }
            if (! Schema::hasColumn('workorder', 'kpi_id')) {
                $table->foreignId('kpi_id')->nullable()->after('status_id')->constrained('m_kpi');
            }
            if (! Schema::hasColumn('workorder', 'tanggal_laporan')) {
                $table->timestamp('tanggal_laporan')->nullable()->after('kpi_id');
            }
            if (! Schema::hasColumn('workorder', 'tanggal_selesai')) {
                $table->timestamp('tanggal_selesai')->nullable()->after('estimasi_selesai');
            }
            if (! Schema::hasColumn('workorder', 'created_by_user_id')) {
                $table->foreignId('created_by_user_id')->nullable()->after('assigned_to')->constrained('users');
            }
            if (! Schema::hasColumn('workorder', 'approved_by_user_id')) {
                $table->foreignId('approved_by_user_id')->nullable()->after('created_by_user_id')->constrained('users');
            }
            if (! Schema::hasColumn('workorder', 'approved_at')) {
                $table->timestamp('approved_at')->nullable()->after('approved_by_user_id');
            }
            if (! Schema::hasColumn('workorder', 'approval_notes')) {
                $table->text('approval_notes')->nullable()->after('approved_at');
            }
        });

        $this->createIndexIfMissing('workorder', 'workorder_assigned_to_status_id_index', ['assigned_to', 'status_id']);
        $this->createIndexIfMissing('workorder', 'workorder_status_id_departemen_id_index', ['status_id', 'departemen_id']);
    }

    private function createWoCategoryTables(): void
    {
        if (! Schema::hasTable('wo_meter')) {
            Schema::create('wo_meter', function (Blueprint $table) {
                $table->id();
                $table->foreignId('workorder_id')->unique()->constrained('workorder')->cascadeOnDelete();
                $table->string('nomor_meter', 64);
                $table->string('kondisi_meter_awal')->nullable();
                $table->string('kondisi_meter_akhir')->nullable();
                $table->string('hasil_kalibrasi')->nullable();
                $table->timestamps();

                $table->index('nomor_meter');
            });
        }

        if (! Schema::hasTable('wo_jaringan')) {
            Schema::create('wo_jaringan', function (Blueprint $table) {
                $table->id();
                $table->foreignId('workorder_id')->unique()->constrained('workorder')->cascadeOnDelete();
                $table->string('jenis_pipa', 64);
                $table->decimal('diameter_pipa', 8, 2)->nullable();
                $table->decimal('panjang_pipa', 10, 2)->nullable();
                $table->string('tingkat_kerusakan', 32)->nullable();
                $table->text('tindakan_perbaikan')->nullable();
                $table->text('hasil_inspeksi')->nullable();
                $table->timestamps();

                $table->index('jenis_pipa');
            });
        }

        if (! Schema::hasTable('wo_infrastruktur')) {
            Schema::create('wo_infrastruktur', function (Blueprint $table) {
                $table->id();
                $table->foreignId('workorder_id')->unique()->constrained('workorder')->cascadeOnDelete();
                $table->string('nama_aset');
                $table->string('jenis_aset', 64);
                $table->string('kapasitas', 64)->nullable();
                $table->text('kondisi_awal')->nullable();
                $table->text('kondisi_akhir')->nullable();
                $table->date('jadwal_pemeliharaan')->nullable();
                $table->text('tindakan')->nullable();
                $table->timestamps();

                $table->index('jenis_aset');
                $table->index('jadwal_pemeliharaan');
            });
        }
    }

    private function alterProgressWorkorderTable(): void
    {
        if (! Schema::hasTable('progress_workorder')) {
            return;
        }

        Schema::table('progress_workorder', function (Blueprint $table) {
            if (! Schema::hasColumn('progress_workorder', 'reviewed_by_user_id')) {
                $table->foreignId('reviewed_by_user_id')->nullable()->after('submitted_by_user_id')->constrained('users');
            }
            if (! Schema::hasColumn('progress_workorder', 'reviewed_at')) {
                $table->timestamp('reviewed_at')->nullable()->after('reviewed_by_user_id');
            }
            if (! Schema::hasColumn('progress_workorder', 'alasan_penolakan')) {
                $table->text('alasan_penolakan')->nullable()->after('order');
            }
            if (! Schema::hasColumn('progress_workorder', 'field_to_revise')) {
                $table->jsonb('field_to_revise')->nullable()->after('alasan_penolakan');
            }
        });
    }

    private function alterDokumentasiProgressTable(): void
    {
        if (! Schema::hasTable('dokumentasi_progress')) {
            return;
        }

        Schema::table('dokumentasi_progress', function (Blueprint $table) {
            if (! Schema::hasColumn('dokumentasi_progress', 'jenis')) {
                $table->string('jenis', 32)->nullable()->after('url');
            }
        });
    }

    private function createLaporanWorkorderTable(): void
    {
        if (Schema::hasTable('laporan_workorder')) {
            return;
        }

        Schema::create('laporan_workorder', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workorder_id')->unique()->constrained('workorder');
            $table->string('nomor_laporan', 64)->unique();
            $table->timestamp('tanggal_terbit');
            $table->text('ringkasan_pekerjaan');
            $table->jsonb('hasil_akhir_snapshot');
            $table->jsonb('petugas_snapshot');
            $table->text('catatan_spv')->nullable();
            $table->foreignId('issued_by_user_id')->constrained('users');
            $table->foreignId('approved_by_user_id')->constrained('users');
            $table->timestamp('approved_at');
            $table->timestamps();
        });
    }

    private function hasIndex(string $table, string $indexName): bool
    {
        $database = DB::getDatabaseName();
        $result = DB::selectOne(
            'SELECT 1 FROM pg_indexes WHERE schemaname = current_schema() AND tablename = ? AND indexname = ?',
            [$table, $indexName]
        );

        return (bool) $result;
    }

    private function createIndexIfMissing(string $table, string $indexName, array $columns): void
    {
        if ($this->hasIndex($table, $indexName)) {
            return;
        }

        Schema::table($table, function (Blueprint $blueprint) use ($columns, $indexName) {
            $blueprint->index($columns, $indexName);
        });
    }
}
