<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book B FIN-03 §2. `calculation_note` is carried verbatim from
 * `learner_fee_lines` — a parent reads the exact same derivation on
 * the invoice that the bursar sees on the fee detail screen.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoice_lines', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->smallInteger('line_number');
            $table->foreignId('component_id')->constrained('fee_components');
            $table->foreignId('fee_line_id')->nullable()->constrained('learner_fee_lines')->nullOnDelete();
            $table->string('description', 255);
            $table->string('calculation_note', 500)->nullable();
            $table->decimal('quantity', 10, 4)->default(1);
            $table->bigInteger('unit_rate_minor')->nullable();
            $table->bigInteger('gross_minor');
            $table->bigInteger('discount_minor')->default(0);
            $table->bigInteger('net_minor');
            $table->char('currency', 3);
            $table->smallInteger('allocation_priority');
            $table->string('tax_category', 20);
            $table->boolean('is_fiscalisable')->default(false);

            $table->index(['school_id', 'invoice_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_lines');
    }
};
