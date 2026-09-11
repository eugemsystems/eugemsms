<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book K ACA-10 §2/BR-ACA-10-002/005/006. `fine_charge_id` FKs to
 * Finance's `ad_hoc_charges` — a late-return fine or a lost-book
 * charge posts through the exact same mechanism as `BRD-05`'s linen
 * loss, not a second library-specific billing path. `borrower_category`
 * is this migration's own addition (not in the spec's literal table):
 * without it, a later `RenewLoanAction` has no reliable way to know
 * which `borrower_categories` row's `max_renewals` governs this loan —
 * a borrower's category could change between issue and renewal, so
 * it's captured once, at issue time, rather than re-derived.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('loans', function (Blueprint $table): void {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('term_id')->constrained();
            $table->foreignId('copy_id')->constrained('library_copies');
            $table->string('borrower_type', 20);
            $table->unsignedBigInteger('borrower_id');
            $table->string('borrower_category', 20);
            $table->date('issued_on');
            $table->date('due_on');
            $table->smallInteger('renewal_count')->default(0);
            $table->date('returned_on')->nullable();
            $table->string('condition_at_return', 20)->nullable();
            $table->string('status', 20);
            $table->foreignId('fine_charge_id')->nullable()->constrained('ad_hoc_charges');
            $table->timestamps();

            $table->index(['school_id', 'borrower_type', 'borrower_id', 'status'], 'loans_borrower_status_idx');
            $table->index(['school_id', 'due_on', 'status'], 'loans_due_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loans');
    }
};
