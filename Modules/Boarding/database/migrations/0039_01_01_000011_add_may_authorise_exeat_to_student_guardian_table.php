<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book F BRD-03 §5/BR-BRD-03-014 — a one-off authorised collector
 * requires explicit authorisation by a guardian holding this right.
 * `student_guardian` is Book C PPL-03's table; this extends it the
 * same way ACA-03 added `staff_id` to `attendance_sessions` — a
 * later module's spec requiring one more column on an earlier
 * module's table.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('student_guardian', function (Blueprint $table): void {
            $table->boolean('may_authorise_exeat')->default(false)->after('may_collect_learner');
        });
    }

    public function down(): void
    {
        Schema::table('student_guardian', function (Blueprint $table): void {
            $table->dropColumn('may_authorise_exeat');
        });
    }
};
