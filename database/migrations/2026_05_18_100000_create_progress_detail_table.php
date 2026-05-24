<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Create progress_detail table to track review history of progress_workorder.
 *
 * Rationale:
 * - progress_workorder represents the work item (photos, description, location)
 * - progress_detail tracks review lifecycle: submit → review → approve/reject → resubmit
 * - One progress can have multiple details (initial + resubmissions after rejection)
 *
 * Migration strategy:
 * 1. Create new table with review-specific fields
 * 2. Keep existing fields in progress_workorder for backward compatibility
 * 3. Future migration can backfill existing review data if needed
 */
class CreateProgressDetailTable extends Migration
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
