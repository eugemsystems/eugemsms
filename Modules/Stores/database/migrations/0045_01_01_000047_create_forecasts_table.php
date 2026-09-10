<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book H1 FIN-11 §2/BR-FIN-11-015. `is_baseline` marks at most one
 * scenario per `(academic_year_id, forecast_type)` as the "Base" case
 * — enforced at the Action layer, not a DB constraint, since a school
 * may legitimately have zero baselines yet (no forecast run).
 * Never touches `budgets`/`budget_lines` — a scenario is data,
 * not a plan (BR-FIN-11-015 ⭐).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('forecasts', function (Blueprint $table): void {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('academic_year_id')->constrained();
            $table->string('forecast_type', 30);
            $table->string('scenario_name', 120);
            $table->json('assumptions');
            $table->json('projections');
            $table->timestamp('generated_at');
            $table->foreignId('generated_by')->constrained('users');
            $table->boolean('is_baseline')->default(false);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('forecasts');
    }
};
