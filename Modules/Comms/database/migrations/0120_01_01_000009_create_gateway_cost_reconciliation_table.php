<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book I COM-01 §2/BR-COM-01-012 (AC-COM-01-006).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gateway_cost_reconciliation', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('gateway_id')->constrained('message_gateways');
            $table->char('period_month', 7);
            $table->bigInteger('system_recorded_minor');
            $table->bigInteger('provider_invoiced_minor')->nullable();
            $table->bigInteger('variance_minor')->nullable();
            $table->unsignedBigInteger('provider_statement_file_id')->nullable();
            $table->string('status', 20);
            $table->foreignId('reconciled_by')->nullable()->constrained('users');
            $table->timestamps();

            $table->unique(['school_id', 'gateway_id', 'period_month'], 'gateway_cost_reconciliation_period_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gateway_cost_reconciliation');
    }
};
