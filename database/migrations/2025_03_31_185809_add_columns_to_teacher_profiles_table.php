<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::table('teacher_profiles', function (Blueprint $table) {
            if (!Schema::hasColumn('teacher_profiles', 'bio')) {
                $table->text('bio')->nullable();
            }
            if (!Schema::hasColumn('teacher_profiles', 'education')) {
                $table->json('education')->nullable();
            }
        });
    }
    
    public function down()
    {
        Schema::table('teacher_profiles', function (Blueprint $table) {
            $table->dropColumn(['bio', 'education']);
        });
    }
};
