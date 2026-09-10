<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book D ACA-05 §3/BR-ACA-05-004. `subjects`' own migration
 * deliberately excluded this column, noting it "belongs to
 * timetabling and assessment (ACA-03/ACA-05)" — added now that ACA-05
 * needs it for the aggregation pipeline's
 * `final = coursework × w + examination × (1 − w)` step.
 * `grading_scale_id` is the subject's own scale; null falls back to
 * the level default (`grading_scales.is_default_for_level`).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subjects', function (Blueprint $table): void {
            $table->decimal('coursework_weight_percent', 5, 2)->nullable()->after('is_active');
            $table->foreignId('grading_scale_id')->nullable()->after('coursework_weight_percent')->constrained('grading_scales')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('subjects', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('grading_scale_id');
            $table->dropColumn('coursework_weight_percent');
        });
    }
};
