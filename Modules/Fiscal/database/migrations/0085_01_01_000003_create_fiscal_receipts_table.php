<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book H3 FIN-13 §3/BR-FIN-13-001/004/005. `source_type`/`source_id`
 * is a plain polymorphic pair (not a dedicated FK) since sources span
 * multiple modules — `Modules\Finance`'s `receipts`,
 * `Modules\Farm`'s `farm_sales`, and (in future passes)
 * `Modules\Facilities`'s hire bookings and `Modules\Wallet`'s sales,
 * none of which this module may depend on directly without inverting
 * the dependency direction. `UNIQUE(source_type, source_id,
 * receipt_type)` is the "exactly one fiscal INVOICE per commercial
 * receipt" guarantee — `receipt_type` is in the key specifically so a
 * later `credit_note` for the same source (`RaiseFiscalCreditNoteAction`,
 * BR-FIN-13-013) isn't blocked by the original `fiscal_invoice` row
 * sharing that same source.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fiscal_receipts', function (Blueprint $table): void {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('device_id')->constrained('fiscal_devices');
            $table->foreignId('fiscal_day_id')->nullable()->constrained('fiscal_days');
            $table->string('source_type', 60);
            $table->unsignedBigInteger('source_id');
            $table->string('receipt_type', 20);
            $table->char('receipt_currency', 3);
            $table->integer('receipt_counter');
            $table->integer('global_counter');
            $table->string('invoice_number', 60);
            $table->timestamp('receipt_date');
            $table->bigInteger('total_minor');
            $table->json('tax_breakdown');
            $table->json('payment_methods');
            $table->string('buyer_name', 200)->nullable();
            $table->text('buyer_tin')->nullable();
            $table->string('buyer_registration', 40)->nullable();
            $table->string('buyer_address', 255)->nullable();
            $table->foreignId('credited_receipt_id')->nullable()->constrained('fiscal_receipts');
            $table->string('credit_reason', 255)->nullable();
            $table->string('receipt_hash', 120)->nullable();
            $table->text('receipt_signature')->nullable();
            $table->string('previous_receipt_hash', 120)->nullable();
            $table->string('verification_code', 80)->nullable();
            $table->string('qr_url', 500)->nullable();
            $table->string('fdms_receipt_id', 60)->nullable();
            $table->string('status', 20);
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->smallInteger('attempt_count')->default(0);
            $table->timestamp('last_attempt_at')->nullable();
            $table->string('error_code', 60)->nullable();
            $table->text('error_message')->nullable();
            $table->json('payload');
            $table->json('response')->nullable();

            $table->unique(['school_id', 'device_id', 'receipt_currency', 'receipt_counter'], 'fiscal_receipts_device_currency_counter_unique');
            $table->unique(['source_type', 'source_id', 'receipt_type'], 'fiscal_receipts_source_unique');
            $table->index(['school_id', 'status', 'receipt_date'], 'fiscal_receipts_school_status_date_idx');
            $table->index(['school_id', 'fiscal_day_id'], 'fiscal_receipts_school_day_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fiscal_receipts');
    }
};
