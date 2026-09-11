<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book J SAA-02 §2/BR-SAA-02-003 — nightly, cross-tenant. Every
 * component that feeds `health_score` is its own stored column, never
 * folded away — the vendor console shows the components, not an
 * opaque number (§3, the same discipline `INT-03`'s risk scores use).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_health_snapshots', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->date('snapshot_date');
            $table->integer('active_schools');
            $table->integer('active_learners');
            $table->string('subscription_status', 20);
            $table->integer('last_login_days_ago')->nullable();
            $table->decimal('module_adoption_percent', 5, 2)->nullable();
            $table->integer('open_support_tickets')->default(0);
            $table->integer('integrity_check_failures')->default(0);
            $table->decimal('health_score', 5, 2)->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'snapshot_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_health_snapshots');
    }
};
