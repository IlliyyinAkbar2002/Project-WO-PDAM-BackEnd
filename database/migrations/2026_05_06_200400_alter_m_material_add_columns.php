<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tambah kolom satuan dan kategori ke m_material.
 */
return new class extends Migration
{
    public function up()
    {
        Schema::table('m_material', function (Blueprint $table) {
            if (! Schema::hasColumn('m_material', 'satuan')) {
                $table->string('satuan', 32)->nullable()->after('terpakai');
            }
            if (! Schema::hasColumn('m_material', 'kategori')) {
                $table->string('kategori', 64)->nullable()->after('satuan');
            }
        });
    }

    public function down()
    {
        Schema::table('m_material', function (Blueprint $table) {
            $table->dropColumn(['satuan', 'kategori']);
        });
    }
};
