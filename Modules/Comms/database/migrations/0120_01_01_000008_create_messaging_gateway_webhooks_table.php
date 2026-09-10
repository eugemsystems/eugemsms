<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book I COM-01 §2/BR-COM-01-010 — APPEND-ONLY, exactly the pattern
 * established in `FIN-05` §5 (`Modules\Finance\Models\GatewayWebhook`).
 * **Renamed from the spec's literal `gateway_webhooks`**: that exact
 * table name is already taken by Book B FIN-05's own webhook table —
 * a genuine naming collision between two books both using generic
 * "gateway" terminology for their own, unrelated webhook streams.
 * `messaging_gateway_webhooks` is unambiguous and keeps both tables.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('messaging_gateway_webhooks', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('gateway_id')->nullable()->constrained('message_gateways')->nullOnDelete();
            $table->string('driver', 30);
            $table->string('event_type', 60)->nullable();
            $table->json('raw_headers');
            $table->longText('raw_payload');
            $table->char('payload_hash', 64);
            $table->boolean('signature_valid');
            $table->foreignId('notification_id')->nullable()->constrained('notifications')->nullOnDelete();
            $table->string('processing_status', 20);
            $table->text('processing_error')->nullable();
            $table->timestamp('received_at', 6);
            $table->timestamp('processed_at')->nullable();

            $table->unique(['driver', 'payload_hash'], 'messaging_gateway_webhooks_replay_unique');
            $table->index(['processing_status', 'received_at'], 'messaging_gateway_webhooks_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('messaging_gateway_webhooks');
    }
};
