<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book B FIN-02 §2. One-off, non-structure charges — a library fine, a
 * damage bill, a uniform sale. `invoice_id` is deferred the same way
 * as `learner_fee_assignments.invoice_id` — no `FIN-03` table to point
 * at yet.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ad_hoc_charges', function (Blueprint $table): void {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('academic_year_id')->constrained();
            $table->foreignId('term_id')->constrained();
            $table->foreignId('student_id')->constrained();
            $table->foreignId('component_id')->constrained('fee_components');
            $table->string('description', 255);
            $table->decimal('quantity', 10, 2)->default(1);
            $table->bigInteger('unit_rate_minor');
            $table->bigInteger('amount_minor');
            $table->char('currency', 3);
            $table->string('source_type', 60)->nullable();
            $table->unsignedBigInteger('source_id')->nullable();
            $table->string('status', 20);
            $table->unsignedBigInteger('invoice_id')->nullable();
            $table->foreignId('raised_by')->constrained('users');
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at');

            $table->index(['school_id', 'student_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ad_hoc_charges');
    }
};
