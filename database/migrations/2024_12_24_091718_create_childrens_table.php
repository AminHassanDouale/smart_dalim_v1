<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('childrens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_profile_id')->constrained()->cascadeOnDelete();
            $table->foreignId('teacher_id')->nullable()->constrained('users');
            $table->string('name');
            $table->string('gender');
            $table->string('school_name');
            $table->string('grade');
            $table->integer('age')->nullable();
            $table->json('available_times');
            $table->timestamp('last_session_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('children_subjects');
        Schema::dropIfExists('childrens');
    }
};
