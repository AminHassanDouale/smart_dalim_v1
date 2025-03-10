<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Models\ClientProfile;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('client_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');

            // Basic information
            $table->string('company_name');
            $table->string('whatsapp');
            $table->string('phone');
            $table->string('website')->nullable();
            $table->string('position');

            // Company details
            $table->string('address');
            $table->string('city');
            $table->string('country');
            $table->string('industry');
            $table->string('company_size');

            // Preferences
            $table->json('preferred_services');
            $table->string('preferred_contact_method');
            $table->text('notes')->nullable();

            // Files
            $table->string('logo')->nullable();

            // Status
            $table->boolean('has_completed_profile')->default(false);
            $table->string('status')->default(ClientProfile::STATUS_PENDING);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('client_profiles');
    }
};
