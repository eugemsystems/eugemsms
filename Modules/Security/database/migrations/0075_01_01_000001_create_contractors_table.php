<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book H2 OPS-06 §2/BR-OPS-06-001. `supplier_id` (`FIN-08`) is a real
 * FK — the module already exists.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contractors', function (Blueprint $table): void {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('supplier_id')->nullable()->constrained('suppliers');
            $table->string('company_name', 200);
            $table->string('contact_person', 150)->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('work_type', 80)->nullable();
            $table->date('insurance_expires_on')->nullable();
            $table->unsignedBigInteger('insurance_file_id')->nullable();
            $table->date('safety_induction_on')->nullable();
            $table->date('induction_valid_until')->nullable();
            $table->date('police_clearance_on')->nullable();
            $table->string('status', 20);
            $table->foreignId('approved_by')->nullable()->constrained('users');

            $table->index(['school_id', 'status', 'induction_valid_until'], 'contractors_status_induction_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contractors');
    }
};
