<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book H3 PPL-05 §2/§3 ⭐ — the tax-treatment flags here
 * (`is_taxable`, `is_pensionable`, `is_nec_applicable`,
 * `is_zimdef_applicable`, `taxable_percent`) are what
 * `StatutoryCalculationEngine` reads to build each base (taxable
 * gross, pensionable gross, ZIMDEF base, NEC base) — never a
 * hard-coded list of component codes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pay_components', function (Blueprint $table): void {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->string('code', 30);
            $table->string('name', 120);
            $table->string('component_type', 20);
            $table->string('category', 30);
            $table->string('calculation_method', 30);
            $table->bigInteger('default_amount_minor')->nullable();
            $table->decimal('default_percent', 6, 3)->nullable();
            $table->string('formula', 500)->nullable();
            $table->char('currency', 3)->nullable();
            $table->boolean('is_taxable')->default(true);
            $table->boolean('is_pensionable')->default(true);
            $table->boolean('is_nec_applicable')->default(true);
            $table->boolean('is_zimdef_applicable')->default(true);
            $table->decimal('taxable_percent', 5, 2)->default(100);
            $table->foreignId('expense_account_id')->nullable()->constrained('accounts')->nullOnDelete();
            $table->foreignId('liability_account_id')->nullable()->constrained('accounts')->nullOnDelete();
            $table->string('cost_centre_source', 20)->default('staff');
            $table->boolean('appears_on_payslip')->default(true);
            $table->smallInteger('sort_order')->nullable();
            $table->boolean('is_active')->default(true);

            $table->unique(['school_id', 'code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pay_components');
    }
};
