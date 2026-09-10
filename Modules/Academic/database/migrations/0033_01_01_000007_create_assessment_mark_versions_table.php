<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book D ACA-05 §2/§4/BR-ACA-05-009 ⭐. Append-only. `was_published`
 * distinguishes a pre-publication correction (moderation) from a
 * post-publication amendment (BR-ACA-05-010, the one that requires
 * approval and cascades a recompute).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assessment_mark_versions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('assessment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained();
            $table->smallInteger('version');
            $table->decimal('raw_mark', 6, 2)->nullable();
            $table->decimal('percent', 5, 2)->nullable();
            $table->string('grade', 10)->nullable();
            $table->string('change_reason', 255)->nullable();
            $table->boolean('was_published')->default(false);
            $table->unsignedBigInteger('approval_request_id')->nullable();
            $table->foreignId('changed_by')->constrained('users');
            $table->timestamp('changed_at');

            $table->unique(['assessment_id', 'student_id', 'version'], 'assessment_mark_versions_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assessment_mark_versions');
    }
};
