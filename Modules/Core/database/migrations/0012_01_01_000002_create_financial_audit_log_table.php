<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book A CORE-08 §2/BR-CORE-08-005..007. Append-only, hash-chained.
 * `Modules\Core\Domain\Support\Sessions\CanonicalPayloadHasher`
 * (CORE-03's snapshot-chain hasher) is reused for `payload_hash` —
 * same canonical-JSON-then-SHA-256 guarantee, one implementation.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('financial_audit_log', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('sequence');
            $table->string('event_type', 50);
            $table->string('subject_type');
            $table->unsignedBigInteger('subject_id');
            $table->bigInteger('amount_minor')->nullable();
            $table->char('amount_currency', 3)->nullable();
            $table->json('payload');
            $table->char('payload_hash', 64);
            $table->char('previous_hash', 64)->nullable();
            $table->foreignId('causer_id')->constrained('users');
            $table->foreignId('impersonator_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('academic_year_id')->constrained();
            $table->foreignId('term_id')->nullable()->constrained()->nullOnDelete();
            $table->string('ip_address', 45)->nullable();
            $table->timestamp('occurred_at', 6);

            $table->unique(['school_id', 'sequence']);
            $table->index(['school_id', 'event_type', 'occurred_at'], 'financial_audit_log_school_event_time_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('financial_audit_log');
    }
};
