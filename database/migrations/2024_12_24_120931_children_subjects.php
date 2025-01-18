<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('children_subjects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('children_id')->constrained()->onDelete('cascade');
            $table->foreignId('subject_id')->constrained()->onDelete('cascade');
            $table->timestamps();

            $table->unique(['children_id', 'subject_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('children_subjects');
    }
};
