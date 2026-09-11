<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book J SAA-03 §2/BR-SAA-03-008 — decomposed exactly as `INT-03`'s
 * learner risk factors are; see `ChurnRiskIndicatorRegistry`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('churn_risk_flags', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->timestamp('flagged_at');
            $table->json('contributing_factors');
            $table->string('status', 20);
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('churn_risk_flags');
    }
};
