<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book K FIN-07 §2 ⭐ — the discount and scholarship catalogue.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('discount_schemes', function (Blueprint $table): void {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->string('code', 30);
            $table->string('name', 150);
            $table->string('scheme_type', 20);
            $table->string('category', 30);
            $table->string('calculation_method', 20);
            $table->json('applies_to_components')->nullable();
            $table->decimal('default_percent', 5, 2)->nullable();
            $table->bigInteger('default_amount_minor')->nullable();
            $table->char('currency', 3)->nullable();
            $table->json('tier_bands')->nullable();
            $table->boolean('requires_means_assessment')->default(false);
            $table->boolean('requires_academic_threshold')->default(false);
            $table->decimal('minimum_average_percent', 5, 2)->nullable();
            $table->boolean('requires_approval')->default(true);
            $table->foreignId('approval_chain_id')->nullable()->constrained('approval_chains')->nullOnDelete();
            $table->boolean('is_sponsor_funded')->default(false);
            $table->foreignId('contra_account_id')->constrained('accounts');
            $table->string('renewal_frequency', 20)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['school_id', 'code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('discount_schemes');
    }
};
