<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book H2 OPS-04 §2/§3 🇿🇼 ⭐/BR-OPS-04-001/002/003. The control that
 * recovers real money — a token stays `purchased` until a human
 * confirms it was actually loaded at the meter.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('prepaid_token_purchases', function (Blueprint $table): void {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('term_id')->constrained();
            $table->foreignId('meter_id')->constrained('meters');
            $table->timestamp('purchased_at');
            $table->string('token_number', 60);
            $table->bigInteger('amount_paid_minor');
            $table->char('currency', 3);
            $table->decimal('units_purchased', 12, 3);
            $table->bigInteger('levies_minor')->default(0);
            $table->bigInteger('effective_rate_minor')->nullable();
            $table->string('vendor', 120)->nullable();
            $table->unsignedBigInteger('receipt_file_id')->nullable();
            $table->foreignId('purchased_by')->constrained('users');
            $table->timestamp('credited_at')->nullable();
            $table->foreignId('credited_by')->nullable()->constrained('users');
            $table->boolean('credit_confirmed')->default(false);
            $table->string('status', 20);
            $table->string('failure_note', 255)->nullable();
            $table->foreignId('journal_id')->nullable()->constrained('journals');

            $table->unique(['school_id', 'token_number'], 'prepaid_tokens_school_number_unique');
            $table->index(['school_id', 'meter_id', 'purchased_at'], 'prepaid_tokens_meter_purchased_idx');
            $table->index(['school_id', 'credit_confirmed', 'status'], 'prepaid_tokens_credit_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('prepaid_token_purchases');
    }
};
