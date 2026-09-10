<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book H1 FIN-08 §2/BR-FIN-08-007/008. `award_justification` is
 * mandatory only when the awarded quotation is not the lowest
 * compliant bid — enforced at the Action layer, not by a DB
 * constraint (the same "note only required conditionally" shape used
 * elsewhere in this codebase, e.g. a stock take's `variance_reason`).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quotation_requests', function (Blueprint $table): void {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('requisition_id')->constrained('purchase_requisitions');
            $table->string('request_number', 40);
            $table->json('suppliers_invited');
            $table->date('issued_on');
            $table->date('closes_on');
            $table->string('status', 20);
            $table->json('evaluation_criteria')->nullable();
            $table->unsignedBigInteger('awarded_quotation_id')->nullable();
            $table->text('award_justification')->nullable();
            $table->foreignId('awarded_by')->nullable()->constrained('users');

            $table->unique(['school_id', 'request_number']);
            $table->index(['requisition_id'], 'quotation_requests_requisition_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quotation_requests');
    }
};
