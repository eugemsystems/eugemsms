<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book I COM-02 §2/BR-COM-02-006 — APPEND-ONLY. Every dispatch
 * attempt writes here, matched or not, sent or throttled — a rule's
 * effectiveness is measurable, never assumed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rule_executions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('rule_id')->constrained('automation_rules');
            $table->string('trigger_source', 60);
            $table->string('subject_type', 60)->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->boolean('matched');
            $table->string('skip_reason', 60)->nullable();
            $table->foreignId('notification_id')->nullable()->constrained('notifications');
            $table->string('variant_key', 20)->nullable();
            $table->timestamp('executed_at', 6);

            $table->index(['school_id', 'rule_id', 'executed_at'], 'rule_executions_rule_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rule_executions');
    }
};
