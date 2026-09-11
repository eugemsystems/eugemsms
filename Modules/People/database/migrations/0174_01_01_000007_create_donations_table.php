<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book K PPL-06 §2/BR-PPL-06-007/008 ⭐/010. Every donation posts
 * through the GL exactly like any other receipt (`journal_id`). This
 * migration adds `bursary_endowment_id` (not in the spec's literal
 * table, which only carries a free-text `restriction_purpose`) — a
 * donation funding a named endowment needs an unambiguous FK to
 * accumulate against, not string-matching on free text (deviation
 * noted per this project's convention). This omits the spec's
 * `receipt_id` FK to `FIN-04` receipts: a donor is not a fee-paying
 * student, so `RecordDonationAction` posts its own journal directly
 * via `PostJournalAction` rather than going through `FIN-04`'s
 * invoice-allocation-oriented `CreateReceiptAction` — there is no
 * receipt row to reference.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('donations', function (Blueprint $table): void {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('term_id')->constrained();
            $table->foreignId('pledge_id')->nullable()->constrained('pledges');
            $table->foreignId('bursary_endowment_id')->nullable()->constrained('bursary_endowments');
            $table->string('donor_name', 200);
            $table->unsignedBigInteger('amount_minor');
            $table->char('currency', 3);
            $table->timestamp('received_at');
            $table->foreignId('journal_id')->nullable()->constrained('journals');
            $table->boolean('is_restricted')->default(false);
            $table->string('restriction_purpose', 255)->nullable();
            $table->timestamps();

            $table->index(['school_id', 'pledge_id'], 'donations_pledge_idx');
            $table->index(['school_id', 'bursary_endowment_id'], 'donations_endowment_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('donations');
    }
};
