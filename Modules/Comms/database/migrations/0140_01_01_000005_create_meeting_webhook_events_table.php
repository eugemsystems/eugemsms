<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book I COM-07 §2/BR-COM-07-006 — the EXACT append-only shape
 * already established for `messaging_gateway_webhooks` (COM-01) and
 * `gateway_webhooks` (FIN-05).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('meeting_webhook_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->nullable()->constrained()->nullOnDelete();
            $table->string('provider', 20);
            $table->string('event_type', 60)->nullable();
            $table->json('raw_headers');
            $table->longText('raw_payload');
            $table->char('payload_hash', 64);
            $table->boolean('signature_valid');
            $table->foreignId('meeting_id')->nullable()->constrained('scheduled_meetings')->nullOnDelete();
            $table->string('processing_status', 20);
            $table->text('processing_error')->nullable();
            $table->timestamp('received_at', 6);
            $table->timestamp('processed_at')->nullable();

            $table->unique(['provider', 'payload_hash'], 'meeting_webhook_events_replay_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meeting_webhook_events');
    }
};
