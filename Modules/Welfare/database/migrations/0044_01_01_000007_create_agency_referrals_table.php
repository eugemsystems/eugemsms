<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book G BRD-08 §2/BR-BRD-08-014 — records the legal basis for
 * sharing information. `reason`/`information_shared`/`outcome` are
 * `SecondaryEncrypted`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agency_referrals', function (Blueprint $table): void {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('case_id')->constrained('safeguarding_cases');
            $table->string('agency_type', 40);
            $table->string('agency_name', 200);
            $table->string('contact_person', 150)->nullable();
            $table->timestamp('referred_at');
            $table->foreignId('referred_by')->constrained('users');
            $table->text('reason');
            $table->text('information_shared')->nullable();
            $table->string('consent_basis', 40);
            $table->timestamp('acknowledgement_at')->nullable();
            $table->string('agency_reference', 80)->nullable();
            $table->text('outcome')->nullable();
            $table->string('status', 20);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agency_referrals');
    }
};
