<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book J SAA-01 §2/BR-SAA-01-003/004 — one row per tenant, per billing
 * month, per metered metric.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('usage_meters', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subscription_id')->constrained();
            $table->char('period_month', 7);
            $table->string('metric', 30);
            $table->decimal('usage_value', 14, 2);
            $table->decimal('limit_value', 14, 2)->nullable();
            $table->boolean('soft_warning_sent')->default(false);
            $table->boolean('hard_limit_reached')->default(false);
            $table->timestamps();

            $table->unique(['tenant_id', 'period_month', 'metric'], 'usage_meters_tenant_period_metric_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('usage_meters');
    }
};
