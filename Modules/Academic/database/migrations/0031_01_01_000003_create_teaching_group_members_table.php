<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book D ACA-02 §2/BR-ACA-02-013 — new to this pass, not in the spec's
 * own literal `teaching_groups` table. The spec tracks `current_count`
 * on `teaching_groups` itself but never defines where the *membership*
 * (which learner is in which set) is actually stored; a denormalised
 * count with no backing rows would be exactly the kind of "second place
 * this number lives" BR-ACA-02-001 forbids for subject counts, so a
 * membership table is added here on the same reasoning. `current_count`
 * remains a cached counter maintained alongside these rows, verified
 * against a `count()` of them.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('teaching_group_members', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('teaching_group_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained();
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->timestamps();

            $table->index(['school_id', 'teaching_group_id'], 'teaching_group_members_group_idx');
            $table->index(['school_id', 'student_id'], 'teaching_group_members_student_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('teaching_group_members');
    }
};
