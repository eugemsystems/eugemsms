<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book K ACA-10 §2/§3 ⭐/BR-ACA-10-007. `exceptions` is this
 * migration's own addition — the spec's literal table only counts
 * exceptions (`exception_count`), but BR-ACA-10-007 requires "a
 * completion report naming every unresolved case", which needs
 * somewhere to store WHICH cases (deviation noted per this project's
 * convention of documenting spec extensions inline).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bulk_textbook_issues', function (Blueprint $table): void {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('term_id')->constrained();
            $table->foreignId('class_id')->constrained('school_classes');
            $table->string('issue_type', 20);
            $table->json('item_ids');
            $table->smallInteger('total_learners');
            $table->smallInteger('completed_count')->default(0);
            $table->smallInteger('exception_count')->default(0);
            $table->json('exceptions')->nullable();
            $table->string('status', 20);
            $table->timestamps();

            $table->index(['school_id', 'class_id', 'term_id'], 'bulk_textbook_issues_class_term_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bulk_textbook_issues');
    }
};
