<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book H3 PPL-05 §2/BR-PPL-05-020/022 ⭐ — auto-prepared on posting
 * with due dates computed from the filing calendar. The system
 * prepares and exports; it never files — `status` never reaches
 * `submitted`/`acknowledged`/`paid` except through a human recording
 * that they did it elsewhere.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('statutory_returns', function (Blueprint $table): void {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->string('return_type', 30);
            $table->string('period_type', 20);
            $table->string('period_reference', 20);
            $table->date('due_date');
            $table->bigInteger('amount_due_minor');
            $table->char('currency', 3);
            $table->json('supporting_data');
            $table->foreignId('export_file_id')->nullable()->constrained('files')->nullOnDelete();
            $table->string('status', 20);
            $table->foreignId('prepared_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('prepared_at')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->string('submission_reference', 80)->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->string('payment_reference', 80)->nullable();
            $table->foreignId('journal_id')->nullable()->constrained('journals')->nullOnDelete();

            $table->unique(['school_id', 'return_type', 'period_reference']);
            $table->index(['school_id', 'due_date', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('statutory_returns');
    }
};
