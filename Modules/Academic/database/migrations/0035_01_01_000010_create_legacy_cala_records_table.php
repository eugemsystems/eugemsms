<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book E ACA-06 §2/BR-ACA-06-019/AC-ACA-06-009. READ-ONLY archive.
 * The spec's own enforcement — revoking INSERT/UPDATE/DELETE for the
 * application database user — is an environment-specific deployment
 * step, not run from this migration; see
 * `Modules\Core\Models\FinancialAuditLogEntry`'s docblock (Book A
 * CORE-08) for the identical reasoning. The model-level guard
 * (`Modules\Academic\Models\LegacyCalaRecord`) blocking update/delete
 * is the layer that's actually testable and always in effect.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('legacy_cala_records', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('academic_year_id')->constrained();
            $table->foreignId('student_id')->constrained();
            $table->foreignId('subject_id')->constrained();
            $table->tinyInteger('cala_number');
            $table->string('title', 200)->nullable();
            $table->decimal('raw_mark', 6, 2)->nullable();
            $table->decimal('max_mark', 6, 2)->nullable();
            $table->decimal('percent', 5, 2)->nullable();
            $table->timestamp('recorded_at')->nullable();
            $table->string('source', 30);

            $table->unique(['school_id', 'academic_year_id', 'student_id', 'subject_id', 'cala_number'], 'legacy_cala_records_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('legacy_cala_records');
    }
};
