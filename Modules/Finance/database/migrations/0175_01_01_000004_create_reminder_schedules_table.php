<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book B FIN-03 §2/BR-FIN-03-015/016. The chase ladder's own rungs — a
 * school defines as many as it wants (0, 7, 14, 30, 60 days after due,
 * per the spec's own example). `escalate_to_role_id` names a Core role
 * (Book A CORE-05) to additionally notify once this rung fires, e.g.
 * "escalate the 60-day rung to the bursar's role" — no dedicated
 * escalation engine is built here, it is just an extra recipient the
 * send job resolves at fire time.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reminder_schedules', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->string('name', 120);
            $table->smallInteger('days_after_due');
            $table->bigInteger('minimum_balance_minor')->default(0);
            $table->char('currency', 3)->nullable();
            $table->json('channels');
            $table->string('template_key', 80);
            $table->string('audience', 30);
            $table->foreignId('escalate_to_role_id')->nullable()->constrained('roles')->nullOnDelete();
            $table->boolean('is_active')->default(true);

            $table->index(['school_id', 'days_after_due']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reminder_schedules');
    }
};
