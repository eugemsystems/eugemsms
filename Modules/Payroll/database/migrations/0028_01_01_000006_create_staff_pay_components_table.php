<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book H3 PPL-05 §2 — the earnings/deductions actually attached to
 * one staff member's pay structure, each dated independently of the
 * structure itself (an allowance can start or end mid-structure).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('staff_pay_components', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('pay_structure_id')->constrained('staff_pay_structures')->cascadeOnDelete();
            $table->foreignId('component_id')->constrained('pay_components');
            $table->bigInteger('amount_minor')->nullable();
            $table->decimal('percent', 6, 3)->nullable();
            $table->char('currency', 3);
            $table->decimal('quantity', 10, 2)->nullable();
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->string('notes', 255)->nullable();

            $table->index('pay_structure_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('staff_pay_components');
    }
};
