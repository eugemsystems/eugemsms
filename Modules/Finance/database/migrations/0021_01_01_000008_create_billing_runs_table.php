<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book B FIN-02 §2/§3/BR-FIN-02-013 ⭐. `computing → preview → approved
 * → committing → committed` (plus `failed`/`cancelled`) are four
 * distinct, human-gated states — a run never commits without an
 * explicit approval of the preview.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('billing_runs', function (Blueprint $table): void {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('academic_year_id')->constrained();
            $table->foreignId('term_id')->constrained();
            $table->json('scope_filter')->nullable();
            $table->string('status', 20);
            $table->integer('total_learners')->default(0);
            $table->integer('computed_count')->default(0);
            $table->integer('exception_count')->default(0);
            $table->bigInteger('total_gross_minor')->default(0);
            $table->bigInteger('total_discount_minor')->default(0);
            $table->bigInteger('total_net_minor')->default(0);
            $table->json('currency_totals')->nullable();
            $table->json('variance_report')->nullable();
            $table->json('exception_report')->nullable();
            $table->foreignId('computed_by')->constrained('users');
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('committed_at')->nullable();
            $table->char('journal_batch_uuid', 36)->nullable();
            $table->timestamps();

            $table->index(['school_id', 'term_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('billing_runs');
    }
};
