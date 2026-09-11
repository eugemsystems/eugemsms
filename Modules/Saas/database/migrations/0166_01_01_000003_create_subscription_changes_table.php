<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book J SAA-01 §2 — APPEND-ONLY upgrade/downgrade history. Model-level
 * guard against update/delete (see `SubscriptionChange`), the same
 * pattern `safeguarding_audit`/`journals` already use in this pass
 * rather than a DB grant REVOKE — see
 * `Modules\Welfare\Models\SafeguardingAuditEntry`'s docblock.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscription_changes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('subscription_id')->constrained()->cascadeOnDelete();
            $table->string('change_type', 20);
            $table->foreignId('from_plan_id')->nullable()->constrained('subscription_plans');
            $table->foreignId('to_plan_id')->nullable()->constrained('subscription_plans');
            $table->bigInteger('proration_credit_minor')->nullable();
            $table->date('effective_from');
            $table->string('reason', 255)->nullable();
            $table->foreignId('performed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('occurred_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_changes');
    }
};
