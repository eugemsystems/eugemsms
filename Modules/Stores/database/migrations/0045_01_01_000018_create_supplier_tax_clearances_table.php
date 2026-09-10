<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book H1 FIN-08 §2/§3 ⭐ — 🇿🇼 ITF263. `document_file_id` is a plain
 * nullable column, no FK (CORE-10 file rows are referenced this way
 * throughout this codebase).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('supplier_tax_clearances', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('supplier_id')->constrained('suppliers');
            $table->string('certificate_number', 60);
            $table->date('issued_on');
            $table->date('expires_on');
            $table->unsignedBigInteger('document_file_id')->nullable();
            $table->foreignId('verified_by')->nullable()->constrained('users');
            $table->timestamp('verified_at')->nullable();
            $table->string('verification_method', 30)->nullable();
            $table->string('status', 20);

            $table->index(['school_id', 'supplier_id', 'expires_on'], 'clearances_supplier_expiry_idx');
            $table->index(['school_id', 'expires_on', 'status'], 'clearances_expiry_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_tax_clearances');
    }
};
