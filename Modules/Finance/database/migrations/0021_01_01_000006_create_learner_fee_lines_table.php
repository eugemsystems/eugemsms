<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book B FIN-02 §2. The computed charge lines. `calculation_note`
 * (BR-FIN-02-009/§4) is the human-readable derivation printed on the
 * invoice and read back to a parent who phones to query their bill.
 * `gross_minor` is never reduced by a discount — `discount_minor` is
 * recorded separately (BR-FIN-02-009).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('learner_fee_lines', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('assignment_id')->constrained('learner_fee_assignments')->cascadeOnDelete();
            $table->foreignId('component_id')->constrained('fee_components');
            $table->foreignId('structure_item_id')->nullable()->constrained('fee_structure_items')->nullOnDelete();
            $table->string('billing_basis', 20);
            $table->decimal('quantity', 10, 4)->default(1);
            $table->bigInteger('unit_rate_minor')->nullable();
            $table->bigInteger('gross_minor');
            $table->decimal('proration_factor', 8, 6)->default(1);
            $table->bigInteger('discount_minor')->default(0);
            $table->bigInteger('net_minor');
            $table->char('currency', 3);
            $table->string('calculation_note', 500)->nullable();
            $table->string('source_reference', 120)->nullable();
            $table->date('effective_from')->nullable();
            $table->timestamps();

            $table->index(['school_id', 'assignment_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('learner_fee_lines');
    }
};
