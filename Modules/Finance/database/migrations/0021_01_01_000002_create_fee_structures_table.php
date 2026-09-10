<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book B FIN-02 §2/BR-FIN-02-011. Versioned — editing an `active`
 * structure creates version n+1, retaining the prior version linked to
 * whatever it already produced. Deliberately `BelongsToSchool` only,
 * not `BelongsToSession`: `term_id` is nullable by design ("applies to
 * all terms in the year"), which `BelongsToSession`'s
 * auto-fill-from-context behaviour on `creating` would silently
 * overwrite. `FeeStructure::booted()` calls `PeriodGuard::assertWritable()`
 * explicitly instead (BR-FIN-02-012), the same way `FIN-01`'s own
 * `Journal` model does.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fee_structures', function (Blueprint $table): void {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('academic_year_id')->constrained();
            $table->foreignId('term_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name', 150);
            $table->smallInteger('version')->default(1);
            $table->string('status', 20);
            $table->smallInteger('priority')->default(100);
            $table->date('effective_from')->nullable();
            $table->date('effective_to')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['school_id', 'academic_year_id', 'term_id', 'status', 'priority'], 'fee_structures_scope_priority_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fee_structures');
    }
};
