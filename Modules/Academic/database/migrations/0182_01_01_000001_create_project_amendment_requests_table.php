<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book E ACA-06 §6, mirroring `mark_amendment_requests` (Book D
 * ACA-05). Amending a `verified` project's mark must route through
 * Core's real CORE-07 approvals engine
 * (`Modules\Core\Domain\Contracts\Approvals\Approvable`) — this table
 * holds the intended new criterion marks while that request is
 * pending. Never itself the source of truth: `ProjectMarkVersion`
 * still owns that once `onApproved()` actually applies the change via
 * `ApplyVerifiedProjectAmendmentAction`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_amendment_requests', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('learner_project_id')->constrained('learner_projects');
            $table->json('new_criterion_marks');
            $table->text('change_reason');
            $table->foreignId('requested_by')->constrained('users');
            $table->string('status', 20)->default('pending');
            $table->foreignId('approval_request_id')->nullable()->constrained('approval_requests');
            $table->timestamp('applied_at')->nullable();
            $table->timestamps();

            $table->index(['school_id', 'learner_project_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_amendment_requests');
    }
};
