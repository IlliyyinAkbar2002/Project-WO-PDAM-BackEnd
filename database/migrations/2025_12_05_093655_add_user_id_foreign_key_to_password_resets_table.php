<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddUserIdForeignKeyToPasswordResetsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('password_resets', function (Blueprint $table) {
            // Add user_id column for One-to-One relationship
            $table->unsignedBigInteger('user_id')->nullable()->after('email');
            
            // Add unique constraint to enforce One-to-One at database level
            $table->unique('user_id');
            
            // Add foreign key constraint from user_id to users.id
            $table->foreign('user_id')
                ->references('id')
                ->on('users')
                ->onDelete('cascade')
                ->onUpdate('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('password_resets', function (Blueprint $table) {
            // Drop the foreign key constraint first
            $table->dropForeign(['user_id']);
            
            // Drop the unique constraint
            $table->dropUnique(['user_id']);
            
            // Drop the column
            $table->dropColumn('user_id');
        });
    }
}
