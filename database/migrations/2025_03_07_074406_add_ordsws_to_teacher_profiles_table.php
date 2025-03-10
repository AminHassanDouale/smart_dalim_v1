<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Check if materials table exists
        if (Schema::hasTable('materials')) {
            // Check if the column doesn't exist yet
            if (!Schema::hasColumn('materials', 'teacher_profile_id')) {
                Schema::table('materials', function (Blueprint $table) {
                    $table->foreignId('teacher_profile_id')
                          ->nullable()
                          ->after('id')
                          ->constrained('teacher_profiles')
                          ->cascadeOnDelete();
                });
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('materials') && Schema::hasColumn('materials', 'teacher_profile_id')) {
            Schema::table('materials', function (Blueprint $table) {
                $table->dropForeign(['teacher_profile_id']);
                $table->dropColumn('teacher_profile_id');
            });
        }
    }
};