<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book J SAA-01 §2 ⭐ — the vendor's contract with a TENANT (a trust, a
 * group, or a single independent school — `Modules\Core\Models\Tenant`),
 * not a school. `covered_school_ids` lets one subscription cover every
 * school a multi-school tenant owns. `status` is the six-state lifecycle
 * §3's `EnsureSubscriptionActive` degrades on; `Tenant::status`
 * (Book A Part 1.10) is kept a denormalised mirror of it so that
 * middleware never joins here on every request — see every Action in
 * this module that writes `status`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscriptions', function (Blueprint $table): void {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('plan_id')->constrained('subscription_plans');
            $table->json('covered_school_ids');
            $table->char('billing_currency', 3);
            $table->integer('learner_count_at_billing')->nullable();
            $table->string('status', 20);
            $table->timestamp('trial_ends_at')->nullable();
            $table->date('current_period_start');
            $table->date('current_period_end');
            $table->timestamp('grace_period_ends_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->string('cancellation_reason', 60)->nullable();
            $table->boolean('auto_renew')->default(true);
            $table->timestamps();

            $table->index(['tenant_id', 'status']);
            $table->index(['status', 'current_period_end']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscriptions');
    }
};
