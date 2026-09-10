<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book H3 PPL-05 §2 — one row per earning/deduction line shown on a
 * payslip, mirroring `invoice_lines`' relationship to `invoices`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payslip_lines', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('payslip_id')->constrained('payslips')->cascadeOnDelete();
            $table->foreignId('component_id')->nullable()->constrained('pay_components')->nullOnDelete();
            $table->string('component_type', 20);
            $table->string('description', 150);
            $table->decimal('quantity', 10, 2)->nullable();
            $table->bigInteger('rate_minor')->nullable();
            $table->bigInteger('amount_minor');
            $table->char('currency', 3);
            $table->boolean('is_taxable');
            $table->smallInteger('sort_order')->nullable();

            $table->index('payslip_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payslip_lines');
    }
};
