<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book H3 FIN-12 §2/BR-FIN-12-014. `period_from`/`period_to` are
 * recorded on every export so a double-export of the same range is
 * detectable.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accounting_exports', function (Blueprint $table): void {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->string('target_system', 30);
            $table->date('period_from');
            $table->date('period_to');
            $table->integer('journal_count');
            $table->unsignedBigInteger('export_file_id');
            $table->foreignId('exported_by')->constrained('users');
            $table->timestamp('exported_at');

            $table->index(['school_id', 'period_from', 'period_to'], 'accounting_exports_school_period_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accounting_exports');
    }
};
