<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book J SAA-01 §2/BR-SAA-01-009 — `gateway_reference` cross-references
 * FIN-05's own `PaymentGatewayDriver::parseWebhook()` output when
 * `payment_method` is `gateway`; this table never runs a second HMAC
 * verification or gateway HTTP integration — see
 * `IngestTenantGatewayWebhookAction`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_payments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('invoice_id')->constrained('tenant_invoices');
            $table->bigInteger('amount_minor');
            $table->char('currency', 3);
            $table->string('payment_method', 30);
            $table->string('gateway_reference', 150)->nullable();
            $table->timestamp('received_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_payments');
    }
};
