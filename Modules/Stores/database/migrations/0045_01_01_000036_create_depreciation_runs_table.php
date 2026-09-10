<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book H1 FIN-10 §2/BR-FIN-10-004/005. One row per calendar month per
 * school — the `UNIQUE(school_id, period_month)` constraint is what
 * actually enforces "a posted period can never run twice"
 * (BR-FIN-10-005), not application logic alone.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('depreciation_runs', function (Blueprint $table): void {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('academic_year_id')->constrained();
            $table->foreignId('term_id')->constrained();
            $table->char('period_month', 7);
            $table->date('run_date');
            $table->integer('asset_count')->default(0);
            $table->bigInteger('total_depreciation_minor')->default(0);
            $table->string('currency', 3);
            $table->string('status', 20);
            $table->foreignId('journal_id')->nullable()->constrained('journals');
            $table->foreignId('computed_by')->constrained('users');
            $table->foreignId('approved_by')->nullable()->constrained('users');
            $table->timestamp('posted_at')->nullable();

            $table->unique(['school_id', 'period_month']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('depreciation_runs');
    }
};
