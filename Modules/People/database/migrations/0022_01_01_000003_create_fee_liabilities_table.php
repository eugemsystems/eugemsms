<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book C PPL-03 §3/§4 ⭐. Owned here, consumed by `FIN-03` — the only
 * table `LiabilityResolver` reads. `agreement_document_id` and
 * `approved_by` are omitted (no document/approval workflow wired for
 * liability changes in this pass).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fee_liabilities', function (Blueprint $table): void {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('guardian_id')->constrained();
            $table->foreignId('component_id')->nullable()->constrained('fee_components')->cascadeOnDelete();
            $table->string('share_type', 20);
            $table->decimal('share_percent', 5, 2)->nullable();
            $table->bigInteger('share_amount_minor')->nullable();
            $table->char('currency', 3)->nullable();
            $table->smallInteger('priority')->default(100);
            $table->foreignId('academic_year_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('term_id')->nullable()->constrained()->nullOnDelete();
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['school_id', 'student_id', 'is_active', 'priority'], 'fee_liabilities_student_active_priority_idx');
            $table->index(['school_id', 'guardian_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fee_liabilities');
    }
};
