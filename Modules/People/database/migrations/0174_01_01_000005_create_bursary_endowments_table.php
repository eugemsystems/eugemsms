<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book K PPL-06 §2/BR-PPL-06-008 ⭐/009. Links a donor to a `FIN-07`
 * discount scheme. `SyncEndowmentBudgetEnvelopeAction` projects this
 * endowment's available balance onto that scheme's own
 * `scheme_budget_envelopes.budget_minor` — `FIN-07`'s own
 * budget-refusal rule (`wouldExceed()`) is never duplicated here.
 * `is_anonymous` is this migration's own addition — the spec lists it
 * only on `pledges`, but BR-PPL-06-009's "named recognition... unless
 * the donor opted for anonymity" is stated about THIS table's own
 * `named_recognition` field, which needs its own anonymity flag to
 * honour (deviation noted per this project's convention).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bursary_endowments', function (Blueprint $table): void {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->string('donor_name', 200);
            $table->foreignId('alumnus_id')->nullable()->constrained('alumni');
            $table->unsignedBigInteger('endowment_capital_minor')->nullable();
            $table->unsignedBigInteger('annual_commitment_minor')->nullable();
            $table->char('currency', 3);
            $table->foreignId('funds_scheme_id')->constrained('discount_schemes');
            $table->string('named_recognition', 200)->nullable();
            $table->boolean('is_anonymous')->default(false);
            $table->date('starts_on');
            $table->string('status', 20);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bursary_endowments');
    }
};
