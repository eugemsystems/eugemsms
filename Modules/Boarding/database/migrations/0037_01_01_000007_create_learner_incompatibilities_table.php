<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book F BRD-01 §2/§3/BR-BRD-01-008 ⭐. `reason` is confidential where
 * flagged — the allocation engine reads `is_active`/`scope` to honour
 * the constraint but the housemaster's own view never surfaces
 * `reason` (enforced at the Livewire/API layer; no screen built yet
 * in this pass to enforce it against).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('learner_incompatibilities', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_a_id')->constrained('students');
            $table->foreignId('student_b_id')->constrained('students');
            $table->string('scope', 20);
            $table->string('reason_category', 40);
            $table->text('reason')->nullable();
            $table->boolean('is_confidential')->default(true);
            $table->foreignId('raised_by')->constrained('users');
            $table->date('expires_on')->nullable();
            $table->boolean('is_active')->default(true);

            $table->unique(['school_id', 'student_a_id', 'student_b_id'], 'learner_incompatibilities_pair_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('learner_incompatibilities');
    }
};
