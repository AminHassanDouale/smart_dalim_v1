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
        Schema::table('parent_profiles', function (Blueprint $table) {
            $table->boolean('newsletter_subscription')->default(true);

            $table->json('notification_preferences')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('parent_profiles', function (Blueprint $table) {
            $table->dropColumn('notification_preferences');
        });
    }
};
