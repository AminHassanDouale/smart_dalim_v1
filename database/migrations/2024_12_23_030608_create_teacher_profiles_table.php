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
        Schema::create('teacher_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('whatsapp')->nullable();;
            $table->string('phone')->nullable();;
            $table->string('fix_number')->nullable();
            $table->string('photo')->nullable();
            $table->date('date_of_birth')->nullable();;
            $table->string('place_of_birth')->nullable();;
            $table->boolean('has_completed_profile')->default(false);
            $table->string('status')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('teacher_profiles');
    }
};