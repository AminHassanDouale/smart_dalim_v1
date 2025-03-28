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
        Schema::create('courses', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description');
            $table->string('level');
            $table->integer('duration')->unsigned(); // Changed to integer to match component
            $table->decimal('price', 10, 2);
            $table->enum('status', ['active', 'inactive', 'draft'])->default('draft');
            $table->foreignId('teacher_profile_id')->constrained('teacher_profiles')->onDelete('cascade');
            $table->foreignId('subject_id')->constrained()->onDelete('cascade');
            $table->json('curriculum');
            $table->json('prerequisites')->nullable(); // Made nullable since it's optional
            $table->json('learning_outcomes');
            $table->integer('max_students')->unsigned();
            $table->date('start_date'); // Changed to date to match component
            $table->date('end_date'); // Changed to date to match component
            $table->string('cover_image')->nullable(); // Added cover_image field
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('courses');
    }
};