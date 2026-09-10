<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book H3 FIN-13 §3/BR-FIN-13-012 — append-only. Every request and
 * response is logged verbatim; when ZIMRA disputes what was sent,
 * this settles it. No `updating`/`deleting` guard is needed at the
 * model layer beyond the same append-only convention this codebase
 * already uses elsewhere (`OccurrenceBookEntry`, `wallet_transactions`)
 * — enforced here too, see `FiscalAuditLogEntry`'s own docblock.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fiscal_audit_log', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('device_id')->nullable()->constrained('fiscal_devices');
            $table->string('event_type', 40);
            $table->string('reference', 80)->nullable();
            $table->json('request_payload')->nullable();
            $table->json('response_payload')->nullable();
            $table->smallInteger('http_status')->nullable();
            $table->integer('duration_ms')->nullable();
            $table->timestamp('occurred_at', 6);

            $table->index(['school_id', 'event_type', 'occurred_at'], 'fiscal_audit_log_school_event_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fiscal_audit_log');
    }
};
