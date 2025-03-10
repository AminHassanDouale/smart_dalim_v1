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
        Schema::create('assessments', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('type')->default('quiz');
            $table->foreignId('teacher_profile_id')->constrained()->onDelete('cascade');
            $table->foreignId('course_id')->nullable()->constrained()->onDelete('set null');
            $table->foreignId('subject_id')->nullable()->constrained()->onDelete('set null');
            $table->integer('total_points')->default(100);
            $table->integer('passing_points')->nullable();
            $table->timestamp('due_date')->nullable();
            $table->timestamp('start_date')->nullable();
            $table->integer('time_limit')->nullable(); // in minutes
            $table->boolean('is_published')->default(false);
            $table->json('settings')->nullable();
            $table->text('instructions')->nullable();
            $table->string('status')->default('draft');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('assessments');
    }
};
