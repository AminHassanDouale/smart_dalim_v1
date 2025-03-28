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
        Schema::table('learning_sessions', function (Blueprint $table) {
            if (!Schema::hasColumn('learning_sessions', 'course_id')) {
                $table->foreignId('course_id')->nullable()->constrained('courses')->onDelete('cascade');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('learning_sessions', function (Blueprint $table) {
            if (Schema::hasColumn('learning_sessions', 'course_id')) {
                $table->dropForeign(['course_id']);
                $table->dropColumn('course_id');
            }
        });
    }
};
