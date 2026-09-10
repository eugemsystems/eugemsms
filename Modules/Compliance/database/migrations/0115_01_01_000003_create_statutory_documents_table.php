<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book H3 CMP-04 §2/BR-CMP-04-005/006. `status` is a plain column
 * refreshed by `CheckStatutoryDocumentExpiryAction` (valid|expiring|
 * expired|renewing), never computed inline on read, so it can be
 * indexed and filtered directly.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('statutory_documents', function (Blueprint $table): void {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->string('document_type', 40);
            $table->string('reference_number', 80)->nullable();
            $table->string('issuing_authority', 150);
            $table->date('issued_on')->nullable();
            $table->date('expires_on')->nullable();
            $table->unsignedBigInteger('file_id')->nullable();
            $table->smallInteger('renewal_lead_days')->default(60);
            $table->foreignId('responsible_staff_id')->nullable()->constrained('staff');
            $table->string('status', 20);
            $table->timestamps();

            $table->index(['school_id', 'status', 'expires_on'], 'statutory_documents_expiry_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('statutory_documents');
    }
};
