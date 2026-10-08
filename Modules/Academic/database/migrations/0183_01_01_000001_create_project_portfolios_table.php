<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book E ACA-06 §6/BR-ACA-06-017 (`Academic\Projects\Portfolio`,
 * `projects.view`). One row per compilation — the same lightweight
 * tracking shape `board_packs` (Book J INT-02) and `report_card_runs`
 * already use for a "generate a document, keep a record of when and
 * by whom" screen: the document itself is the source of truth, this
 * is just the audit trail of it existing.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_portfolios', function (Blueprint $table): void {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('learner_project_id')->constrained('learner_projects');
            $table->foreignId('document_id')->nullable()->constrained('documents');
            $table->foreignId('compiled_by')->constrained('users');
            $table->timestamp('compiled_at');

            $table->index(['school_id', 'learner_project_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_portfolios');
    }
};
