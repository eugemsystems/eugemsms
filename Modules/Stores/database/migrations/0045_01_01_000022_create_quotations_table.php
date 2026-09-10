<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quotations', function (Blueprint $table): void {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('quotation_request_id')->constrained('quotation_requests');
            $table->foreignId('supplier_id')->constrained('suppliers');
            $table->string('quotation_reference', 60)->nullable();
            $table->date('received_on');
            $table->date('valid_until')->nullable();
            $table->bigInteger('subtotal_minor');
            $table->bigInteger('tax_minor')->default(0);
            $table->bigInteger('total_minor');
            $table->string('currency', 3);
            $table->smallInteger('delivery_days')->nullable();
            $table->smallInteger('payment_terms_days')->nullable();
            $table->unsignedBigInteger('document_file_id')->nullable();
            $table->decimal('evaluation_score', 6, 2)->nullable();
            $table->boolean('is_compliant')->default(true);
            $table->string('non_compliance_note', 255)->nullable();
            $table->string('status', 20);

            $table->index(['quotation_request_id'], 'quotations_request_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quotations');
    }
};
