<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book K FIN-07 §2/BR-FIN-07-009 ⭐. `budget_minor` null = uncapped
 * (e.g. staff-child, sibling — schemes a school commits to
 * unconditionally, not a pool it can exhaust).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('scheme_budget_envelopes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('scheme_id')->constrained('discount_schemes')->cascadeOnDelete();
            $table->foreignId('academic_year_id')->constrained();
            $table->bigInteger('budget_minor')->nullable();
            $table->char('currency', 3)->nullable();
            $table->bigInteger('committed_minor')->default(0);
            $table->bigInteger('utilised_minor')->default(0);
            $table->timestamps();

            $table->unique(['school_id', 'scheme_id', 'academic_year_id'], 'scheme_budget_envelopes_school_scheme_year_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scheme_budget_envelopes');
    }
};
