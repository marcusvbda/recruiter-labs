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
        Schema::create('interviews', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('application_id')->constrained()->cascadeOnDelete();
            $table->foreignId('calendar_user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('calendar_integration_id')
                ->nullable()
                ->constrained('connected_integrations')
                ->nullOnDelete();
            $table->string('status')->default('pending');
            $table->timestamp('scheduled_at');
            $table->timestamp('ends_at');
            $table->string('timezone', 64);
            $table->string('calendar_event_id')->unique();
            $table->string('calendar_conference_id')->nullable();
            $table->string('meeting_url')->nullable();
            $table->string('rsvp_status')->default('needs_action');
            $table->timestamp('rsvp_responded_at')->nullable();
            $table->unsignedInteger('notification_sequence')->default(0);
            $table->string('pending_notification_type')->nullable();
            $table->string('calendar_sync_status')->default('pending');
            $table->boolean('calendar_sync_terminal')->default(false);
            $table->text('calendar_sync_error')->nullable();
            $table->timestamp('last_calendar_synced_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->text('cancellation_reason')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'status', 'scheduled_at']);
            $table->index(['calendar_sync_status', 'scheduled_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('interviews');
    }
};
