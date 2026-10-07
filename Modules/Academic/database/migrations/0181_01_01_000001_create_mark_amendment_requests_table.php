<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book D ACA-05 §4/BR-ACA-05-010. Amending a mark on a `published`
 * assessment must route through Core's real CORE-07 approvals engine
 * (`Modules\Core\Domain\Contracts\Approvals\Approvable`) — this table
 * holds the intended new value while that request is pending, the
 * same shape `discount_awards` uses for its own CORE-07 gate
 * (`approval_request_id`, applied only from `onApproved()`). Never
 * itself the source of truth for the mark: `AssessmentMarkVersion`
 * still owns that once `onApproved()` actually applies the change via
 * `AmendMarkAction`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mark_amendment_requests', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('assessment_id')->constrained('assessments');
            $table->foreignId('student_id')->constrained('students');
            $table->decimal('new_raw_mark', 6, 2)->nullable();
            $table->boolean('new_is_absent')->default(false);
            $table->text('change_reason');
            $table->foreignId('requested_by')->constrained('users');
            $table->string('status', 20)->default('pending');
            $table->foreignId('approval_request_id')->nullable()->constrained('approval_requests');
            $table->timestamp('applied_at')->nullable();
            $table->timestamps();

            $table->index(['school_id', 'assessment_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mark_amendment_requests');
    }
};
