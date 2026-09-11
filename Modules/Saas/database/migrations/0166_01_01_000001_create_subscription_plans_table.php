<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book J SAA-01 §2. The vendor's own plan catalogue — a tier a
 * tenant's `subscriptions` row points at. Not tenant data; there is no
 * `school_id`/`tenant_id` here, the same way `report_entities` (INT-01)
 * carries none.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscription_plans', function (Blueprint $table): void {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->string('code', 30)->unique();
            $table->string('name', 120);
            $table->string('tier', 20);
            $table->bigInteger('price_per_learner_minor')->nullable();
            $table->bigInteger('flat_monthly_minor')->nullable();
            $table->char('currency', 3);
            $table->json('included_modules');
            $table->integer('learner_band_min')->nullable();
            $table->integer('learner_band_max')->nullable();
            $table->integer('seat_limit_admin')->nullable();
            $table->integer('storage_quota_gb')->nullable();
            $table->integer('message_quota_monthly')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_plans');
    }
};
