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
            if (!Schema::hasColumn('learning_sessions', 'date')) {
                $table->date('date')->nullable();
            }
            
            if (!Schema::hasColumn('learning_sessions', 'start_time')) {
                $table->time('start_time')->nullable();
            }
            
            if (!Schema::hasColumn('learning_sessions', 'end_time')) {
                $table->time('end_time')->nullable();
            }
            
            if (!Schema::hasColumn('learning_sessions', 'title')) {
                $table->string('title')->nullable();
            }
            
            if (!Schema::hasColumn('learning_sessions', 'status')) {
                $table->string('status')->default('scheduled');
            }
            
            if (!Schema::hasColumn('learning_sessions', 'description')) {
                $table->text('description')->nullable();
            }
            
            if (!Schema::hasColumn('learning_sessions', 'recording_url')) {
                $table->string('recording_url')->nullable();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('learning_sessions', function (Blueprint $table) {
            $table->dropColumn([
                'date',
                'start_time',
                'end_time',
                'title',
                'status',
                'description',
                'recording_url'
            ]);
        });
    }
};