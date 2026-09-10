<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book I COM-02 §2/BR-COM-02-003/004. `field` is always validated
 * against `AutomationEntityRegistry`'s whitelist for the rule's own
 * `scan_entity` (or the event's payload shape) at save time —
 * `ConditionEvaluator` refuses to construct against anything else.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rule_conditions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('rule_id')->constrained('automation_rules')->cascadeOnDelete();
            $table->smallInteger('group_id')->default(1);
            $table->string('group_logic', 5)->default('AND');
            $table->string('field', 80);
            $table->string('operator', 20);
            $table->json('value');
            $table->smallInteger('sort_order')->nullable();
            $table->timestamps();

            $table->index(['rule_id', 'group_id'], 'rule_conditions_group_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rule_conditions');
    }
};
