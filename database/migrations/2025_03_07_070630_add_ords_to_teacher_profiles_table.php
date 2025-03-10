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
        Schema::table('teacher_profiles', function (Blueprint $table) {
            $table->string('available_days')->default('1,2,3,4,5'); // Default to weekdays
            $table->time('available_time_start')->default('08:00:00');
            $table->time('available_time_end')->default('18:00:00');
            $table->time('break_time_start')->default('12:00:00');
            $table->time('break_time_end')->default('13:00:00');

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('teacher_profiles', function (Blueprint $table) {
            $table->dropColumn([
                'available_days',
                'available_time_start',
                'available_time_end',
                'break_time_start',
                'break_time_end',
            ]);
        });
    }
};
