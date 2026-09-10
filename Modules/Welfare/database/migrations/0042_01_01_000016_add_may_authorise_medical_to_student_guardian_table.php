<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book G BRD-06 §2/BR-BRD-06-011 ⭐ — a right independent of
 * `may_collect_learner`/`may_authorise_exeat`, following the same
 * per-right-not-inferred pattern PPL-03 already established. Mirrors
 * `BRD-03`'s identical `add_may_authorise_exeat_to_student_guardian_table`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('student_guardian', function (Blueprint $table): void {
            $table->boolean('may_authorise_medical')->default(false)->after('may_authorise_exeat');
        });
    }

    public function down(): void
    {
        Schema::table('student_guardian', function (Blueprint $table): void {
            $table->dropColumn('may_authorise_medical');
        });
    }
};
