<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('supplier_contracts', function (Blueprint $table): void {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('supplier_id')->constrained('suppliers');
            $table->string('contract_number', 40);
            $table->string('title', 200);
            $table->string('contract_type', 30);
            $table->date('starts_on');
            $table->date('ends_on')->nullable();
            $table->bigInteger('value_minor')->nullable();
            $table->string('currency', 3)->nullable();
            $table->smallInteger('renewal_notice_days')->nullable();
            $table->boolean('auto_renew')->default(false);
            $table->unsignedBigInteger('document_file_id')->nullable();
            $table->foreignId('owner_staff_id')->nullable()->constrained('staff');
            $table->string('status', 20);

            $table->unique(['school_id', 'contract_number']);
            $table->index(['school_id', 'ends_on', 'status'], 'contracts_expiry_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_contracts');
    }
};
