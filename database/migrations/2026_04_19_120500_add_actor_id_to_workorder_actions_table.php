<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddActorIdToWorkorderActionsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * TKT-06: tambah audit trail "siapa yang melakukan aksi ini" di
     * `workorder_action`. Saat ini setiap aksi (PENUGASAN / FREEZE / RESUME
     * / EXTEND) tercatat tanpa pelaku, sehingga tidak bisa dijawab pertanyaan
     * dasar seperti "siapa yang freeze WO ini?" atau "siapa SPV yang
     * menugaskan?".
     *
     * Nullable sengaja dibiarkan untuk kompatibilitas dengan row lama yang
     * dibuat sebelum migration ini jalan — mereka memang tidak punya pelaku
     * tercatat dan akan tetap null (tidak di-backfill karena kita tidak bisa
     * menebak siapa actor historis). Semua insert baru dari controller/service
     * akan selalu mengisi kolom ini (lihat `WorkorderActionController::store`,
     * `WorkorderService::createWorkorders`, dan `LemburSplController::update`).
     *
     * @return void
     */
    public function up()
    {
        Schema::table('workorder_action', function (Blueprint $table) {
            $table->foreignId('actor_id')
                ->nullable()
                ->after('action_id')
                ->constrained('users');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('workorder_action', function (Blueprint $table) {
            $table->dropConstrainedForeignId('actor_id');
        });
    }
}
