<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book H1 FIN-11 §2/BR-FIN-11-002. Versioned — approving a revision
 * never overwrites the prior row; it creates a new one with an
 * incremented `version`, and both stay retrievable
 * (`UNIQUE(school_id, academic_year_id, name, version)`).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('budgets', function (Blueprint $table): void {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('academic_year_id')->constrained();
            $table->string('name', 150);
            $table->string('budget_type', 20);
            $table->string('period_basis', 20);
            $table->string('currency', 3);
            $table->smallInteger('version')->default(1);
            $table->string('status', 20);
            $table->bigInteger('total_income_minor')->default(0);
            $table->bigInteger('total_expense_minor')->default(0);
            $table->bigInteger('surplus_minor')->default(0);
            $table->unsignedBigInteger('approval_request_id')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users');
            $table->timestamp('approved_at')->nullable();
            $table->date('board_approved_on')->nullable();
            $table->foreignId('prepared_by')->constrained('users');
            $table->timestamps();

            $table->unique(['school_id', 'academic_year_id', 'name', 'version']);
            $table->index(['school_id', 'academic_year_id', 'status'], 'budgets_year_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('budgets');
    }
};
