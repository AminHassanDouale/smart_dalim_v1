<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('assessment_submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('assessment_id')->constrained()->onDelete('cascade');
            $table->foreignId('children_id')->nullable()->constrained('childrens')->onDelete('cascade');
            $table->foreignId('client_profile_id')->nullable()->constrained('client_profiles')->onDelete('cascade');
            $table->timestamp('start_time')->nullable();
            $table->timestamp('end_time')->nullable();
            $table->integer('score')->nullable();
            $table->string('status')->default('not_started'); // not_started, in_progress, completed, graded
            $table->json('answers')->nullable();
            $table->json('feedback')->nullable();
            $table->foreignId('graded_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('graded_at')->nullable();
            $table->timestamps();

            // Unique constraints
            $table->unique(['assessment_id', 'children_id'], 'unique_assessment_child');
            $table->unique(['assessment_id', 'client_profile_id'], 'unique_assessment_client');
        });

        // Add check constraint using raw SQL
        DB::statement('ALTER TABLE assessment_submissions ADD CONSTRAINT check_either_child_or_client CHECK
            ((children_id IS NOT NULL AND client_profile_id IS NULL) OR (children_id IS NULL AND client_profile_id IS NOT NULL))');

        // Assessment material pivot table
        Schema::create('assessment_material', function (Blueprint $table) {
            $table->id();
            $table->foreignId('assessment_id')->constrained()->onDelete('cascade');
            $table->foreignId('material_id')->constrained()->onDelete('cascade');
            $table->timestamps();

            // Unique constraint
            $table->unique(['assessment_id', 'material_id']);
        });

        // Assessment children pivot table
        Schema::create('assessment_children', function (Blueprint $table) {
            $table->id();
            $table->foreignId('assessment_id')->constrained()->onDelete('cascade');
            $table->foreignId('children_id')->constrained('childrens')->onDelete('cascade');
            $table->string('status')->default('not_started'); // not_started, in_progress, completed, graded
            $table->timestamp('start_time')->nullable();
            $table->timestamp('end_time')->nullable();
            $table->integer('score')->nullable();
            $table->json('metadata')->nullable(); // For additional data
            $table->timestamps();

            // Unique constraint
            $table->unique(['assessment_id', 'children_id']);
        });

        Schema::create('assessment_client', function (Blueprint $table) {
            $table->id();
            $table->foreignId('assessment_id')->constrained()->onDelete('cascade');
            $table->foreignId('client_profile_id')->constrained()->onDelete('cascade');
            $table->string('status')->default('not_started'); // not_started, in_progress, completed, graded
            $table->timestamp('start_time')->nullable();
            $table->timestamp('end_time')->nullable();
            $table->integer('score')->nullable();
            $table->json('metadata')->nullable(); // For additional data
            $table->timestamps();

            // Unique constraint
            $table->unique(['assessment_id', 'client_profile_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop the check constraint if it exists (for MySQL)
        // For PostgreSQL, the constraint is automatically dropped with the table
        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE assessment_submissions DROP CONSTRAINT IF EXISTS check_either_child_or_client');
        }

        Schema::dropIfExists('assessment_client');
        Schema::dropIfExists('assessment_children');
        Schema::dropIfExists('assessment_material');
        Schema::dropIfExists('assessment_submissions');
    }
};