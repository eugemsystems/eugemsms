<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book J INT-01 §2 ⭐/BR-INT-01-009. Append-only — every execution, ad
 * hoc or scheduled, saved or thrown away.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('report_executions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('report_id')->nullable()->constrained('custom_reports')->nullOnDelete();
            $table->foreignId('executed_by')->constrained('users');
            $table->integer('row_count')->nullable();
            $table->integer('duration_ms');
            $table->json('schools_included')->nullable();
            $table->timestamp('executed_at', 6);

            $table->index(['school_id', 'executed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('report_executions');
    }
};
