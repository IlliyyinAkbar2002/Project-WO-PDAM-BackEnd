<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;


class CreateProgressDetail extends Migration
{
    public function up()
    {
        Schema::create('progress_detail', function (Blueprint $table) {
            $table->id();
            $table->foreignId('progress_workorder_id')
                ->constrained('progress_workorder')
                ->cascadeOnDelete();

            // Status: 'pending', 'approved', 'rejected'
            $table->string('status', 32)->default('pending');

            // Review metadata
            $table->foreignId('reviewed_by_user_id')
                ->nullable()
                ->constrained('users');
            $table->timestamp('reviewed_at')->nullable();

            // Rejection metadata
            $table->text('alasan_penolakan')->nullable();
            $table->string('field_to_revise')->nullable()
                ->comment('Comma-separated: photo,description,location,etc');

            $table->timestamps();

            // Indexes for common queries
            $table->index('status');
            $table->index('reviewed_at');
            $table->index(['progress_workorder_id', 'status']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('progress_detail');
    }
}
