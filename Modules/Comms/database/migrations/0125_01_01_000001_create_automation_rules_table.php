<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book I COM-02 §2/BR-COM-02-001/008 ⭐. `estimated_monthly_cost_minor`
 * is null until reviewed — `ActivateAutomationRuleAction` refuses to
 * flip `is_active` while it's null (BR-COM-02-008, AC-COM-02-005).
 *
 * **Simplification**: the spec's own `condition_expression JSON`
 * column on this table is not created — it would duplicate
 * `rule_conditions`, the normalized table that already holds the
 * exact same condition data in queryable, field-whitelist-validatable
 * rows (what the visual rule builder actually needs). `rule_conditions`
 * is the single source of truth for a rule's conditions in this pass.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('automation_rules', function (Blueprint $table): void {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->string('name', 150);
            $table->string('notification_key', 80);
            $table->string('trigger_type', 20);
            $table->string('event_name', 80)->nullable();
            $table->string('schedule_cron', 60)->nullable();
            $table->string('scan_entity', 40)->nullable();
            $table->string('audience_override', 30)->nullable();
            $table->json('channel_override')->nullable();
            $table->string('template_key_override', 80)->nullable();
            $table->integer('delay_minutes')->default(0);
            $table->string('throttle_key', 120)->nullable();
            $table->integer('throttle_window_hours')->nullable();
            $table->boolean('is_active')->default(false);
            $table->bigInteger('estimated_monthly_cost_minor')->nullable();
            $table->char('estimated_monthly_currency', 3)->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->foreignId('updated_by')->nullable()->constrained('users');
            $table->timestamps();

            $table->unique(['school_id', 'name'], 'automation_rules_name_unique');
            $table->index(['school_id', 'trigger_type', 'is_active'], 'automation_rules_trigger_idx');
            $table->index(['school_id', 'event_name'], 'automation_rules_event_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('automation_rules');
    }
};
