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
        // Support Tickets Table
        Schema::create('support_tickets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('ticket_id')->unique();
            $table->string('title');
            $table->text('description');
            $table->string('category')->index();
            $table->string('priority')->default('medium');
            $table->string('status')->default('open')->index();
            $table->string('related_entity_type')->nullable();
            $table->unsignedBigInteger('related_entity_id')->nullable();
            $table->timestamp('first_response_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->boolean('closed_by_user')->default(false);
            $table->timestamp('reopened_at')->nullable();
            $table->integer('satisfaction_rating')->nullable();
            $table->text('satisfaction_comment')->nullable();
            $table->timestamp('rated_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            // Index for related entity
            $table->index(['related_entity_type', 'related_entity_id']);
        });

        // Support Messages Table
        Schema::create('support_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('support_ticket_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedBigInteger('admin_id')->nullable();
            $table->text('message');
            $table->string('message_type')->default('user');
            $table->boolean('is_internal')->default(false);
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            // Foreign key for admin_id
            $table->foreign('admin_id')->references('id')->on('users')->nullOnDelete();
        });

        // Support Attachments Table
        Schema::create('support_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('support_ticket_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('support_message_id')->nullable()->constrained()->nullOnDelete();
            $table->string('file_path');
            $table->string('file_name');
            $table->unsignedBigInteger('file_size');
            $table->string('file_type');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('support_attachments');
        Schema::dropIfExists('support_messages');
        Schema::dropIfExists('support_tickets');
    }
};
