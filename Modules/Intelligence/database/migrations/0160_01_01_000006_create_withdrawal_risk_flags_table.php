<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book J INT-03 §2/BR-INT-03-008. Closing a flag with no
 * `intervention_note` is refused at the Action layer
 * (`ReviewWithdrawalRiskFlagAction`) — every flag ends in either a
 * documented action or a documented decision not to act.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('withdrawal_risk_flags', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained();
            $table->timestamp('flagged_at');
            $table->json('contributing_factors');
            $table->string('status', 20)->default('open');
            $table->foreignId('reviewed_by')->nullable()->constrained('users');
            $table->text('intervention_note')->nullable();
            $table->timestamps();

            $table->index(['school_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('withdrawal_risk_flags');
    }
};
