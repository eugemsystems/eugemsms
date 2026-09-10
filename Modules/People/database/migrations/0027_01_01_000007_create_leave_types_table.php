<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book C PPL-04 §2/§4.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leave_types', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->string('code', 20);
            $table->string('name', 80);
            $table->decimal('annual_entitlement_days', 5, 1)->nullable();
            $table->string('accrual_method', 20);
            $table->boolean('is_paid')->default(true);
            $table->boolean('requires_document')->default(false);
            $table->smallInteger('max_consecutive_days')->nullable();
            $table->decimal('carry_forward_days', 5, 1)->nullable();
            $table->boolean('requires_cover')->default(true);
            $table->json('applies_to_categories')->nullable();
            $table->boolean('is_active')->default(true);

            $table->unique(['school_id', 'code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leave_types');
    }
};
