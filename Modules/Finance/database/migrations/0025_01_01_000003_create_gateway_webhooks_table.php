<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book B FIN-05 §3/BR-FIN-05-004/005. Raw, append-only. Replay
 * protection is `UNIQUE (driver, payload_hash)` — a duplicate delivery
 * fails that constraint and is recorded as `duplicate`, not processed
 * again.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gateway_webhooks', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('gateway_id')->nullable()->constrained('payment_gateways')->nullOnDelete();
            $table->string('driver', 30);
            $table->string('event_type', 60)->nullable();
            $table->json('raw_headers');
            $table->longText('raw_payload');
            $table->char('payload_hash', 64);
            $table->boolean('signature_valid');
            $table->foreignId('intent_id')->nullable()->constrained('payment_intents')->nullOnDelete();
            $table->string('processing_status', 20);
            $table->text('processing_error')->nullable();
            $table->timestamp('received_at', 6);
            $table->timestamp('processed_at')->nullable();

            $table->unique(['driver', 'payload_hash']);
            $table->index(['processing_status', 'received_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gateway_webhooks');
    }
};
