<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book E ACA-06 §2/BR-ACA-06-011. Append-only — see
 * `Modules\Academic\Models\AssessmentMarkVersion` (Book D ACA-05) for
 * the identical no-mutable-column convention this mirrors.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_mark_versions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('learner_project_id')->constrained('learner_projects')->cascadeOnDelete();
            $table->smallInteger('version');
            $table->decimal('raw_mark', 6, 2)->nullable();
            $table->json('criterion_marks')->nullable();
            $table->string('stage', 20);
            $table->string('change_reason', 255)->nullable();
            $table->foreignId('changed_by')->constrained('users');
            $table->timestamp('changed_at');

            $table->unique(['learner_project_id', 'version'], 'project_mark_versions_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_mark_versions');
    }
};
