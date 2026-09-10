<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book B FIN-05 §3/BR-FIN-05-001/002. A payment intent is not a
 * receipt — no journal posts until a driver confirms settlement.
 * `payer_user_id` is a plain reference (no FK) since it may point to
 * a guardian's portal user or a student's, and this pass doesn't need
 * to join on it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_intents', function (Blueprint $table): void {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('academic_year_id')->constrained();
            $table->foreignId('term_id')->constrained();
            $table->foreignId('gateway_id')->constrained('payment_gateways');
            $table->string('reference', 80)->unique();
            $table->char('idempotency_key', 36)->unique();
            $table->foreignId('student_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedBigInteger('payer_user_id')->nullable();
            $table->string('payer_name', 200);
            $table->string('payer_phone', 30)->nullable();
            $table->string('payer_email', 150)->nullable();
            $table->string('purpose', 30);
            $table->bigInteger('amount_minor');
            $table->char('currency', 3);
            $table->string('method', 30)->nullable();
            $table->string('status', 20);
            $table->string('gateway_reference', 150)->nullable();
            $table->string('poll_url', 500)->nullable();
            $table->string('checkout_url', 500)->nullable();
            $table->text('instructions')->nullable();
            $table->string('failure_code', 60)->nullable();
            $table->text('failure_message')->nullable();
            $table->bigInteger('fee_minor')->nullable();
            $table->bigInteger('net_settled_minor')->nullable();
            $table->foreignId('receipt_id')->nullable()->constrained('receipts')->nullOnDelete();
            $table->timestamp('initiated_at');
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('expires_at');
            $table->smallInteger('poll_attempts')->default(0);
            $table->timestamp('last_polled_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['school_id', 'status', 'initiated_at']);
            $table->index(['student_id', 'status']);
            $table->index('gateway_reference');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_intents');
    }
};
