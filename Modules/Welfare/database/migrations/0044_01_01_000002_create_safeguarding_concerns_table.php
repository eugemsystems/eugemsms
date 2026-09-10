<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book G BRD-08 §2/§4 ⭐⭐/BR-BRD-08-009/011. `reporter_user_id` is
 * NULL for anonymous reports — deliberately absent, not encrypted, not
 * hashed. `description` is `SecondaryEncrypted`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('safeguarding_concerns', function (Blueprint $table): void {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->nullable()->constrained();
            $table->timestamp('reported_at');
            $table->string('report_source', 30);
            $table->foreignId('reporter_user_id')->nullable()->constrained('users');
            $table->char('anonymous_token', 26)->nullable();
            $table->string('concern_category', 40);
            $table->text('description');
            $table->boolean('immediate_risk')->default(false);
            $table->text('initial_action_taken')->nullable();
            $table->foreignId('case_id')->nullable()->constrained('safeguarding_cases');
            $table->string('triage_status', 20);
            $table->foreignId('triaged_by')->nullable()->constrained('users');
            $table->timestamp('triaged_at')->nullable();
            $table->text('triage_rationale')->nullable();

            $table->index(['school_id', 'triage_status', 'reported_at'], 'concerns_triage_idx');
            $table->index(['school_id', 'immediate_risk', 'triage_status'], 'concerns_risk_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('safeguarding_concerns');
    }
};
