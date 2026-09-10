<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book D ACA-05 §2/§6 ⭐/AC-ACA-05-011. `template_id` has no FK
 * constraint — `document_templates` lives in Core and the two modules
 * have no existing dependency in either direction (Academic never
 * imports Finance's equivalent pattern for the same reason; keeping
 * this a plain reference avoids a new one). Per-learner outcomes are
 * not modelled as a child table in this pass — see
 * `RunReportCardBatchAction`'s own docblock for the scope boundary.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('report_card_runs', function (Blueprint $table): void {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('term_id')->constrained();
            $table->json('scope_filter')->nullable();
            $table->unsignedBigInteger('template_id');
            $table->smallInteger('template_version');
            $table->integer('total_count')->default(0);
            $table->integer('generated_count')->default(0);
            $table->integer('withheld_count')->default(0);
            $table->integer('failed_count')->default(0);
            $table->string('status', 20);
            $table->unsignedBigInteger('merged_document_id')->nullable();
            $table->foreignId('requested_by')->constrained('users');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();

            $table->index(['school_id', 'term_id', 'status'], 'report_card_runs_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('report_card_runs');
    }
};
