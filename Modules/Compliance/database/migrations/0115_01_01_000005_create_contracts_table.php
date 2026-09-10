<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book H3 CMP-04 §1/§2 (⚠ added during implementation, see below).
 * **Correction made during CMP-04 implementation**: the spec's own
 * "abridged" data model for this module names "contract register" in
 * its scope line but never defines a table for it, and no business
 * rule (`BR-CMP-04-001`..`008`) references one either. Added here,
 * minimally: general school contracts — vendors, service providers,
 * leases — distinct from `staff_contracts` (Book C PPL-04, employment
 * contracts), which already exists. Expiry alerting reuses the SAME
 * mechanism as `statutory_documents` (`CheckDocumentExpiryAction`
 * covers both), since nothing in the spec distinguishes their alerting
 * needs.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contracts', function (Blueprint $table): void {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->string('counterparty_name', 200);
            $table->string('contract_type', 40);
            $table->text('description')->nullable();
            $table->date('starts_on');
            $table->date('expires_on')->nullable();
            $table->unsignedBigInteger('document_file_id')->nullable();
            $table->foreignId('responsible_staff_id')->nullable()->constrained('staff');
            $table->string('status', 20);
            $table->timestamps();

            $table->index(['school_id', 'status', 'expires_on'], 'contracts_expiry_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contracts');
    }
};
