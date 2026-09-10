<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book H2 OPS-01 §2 🇿🇼/BR-OPS-01-001/002 ⭐. Each compliance type
 * (vehicle licence, ZINARA, certificate of fitness, insurance,
 * passenger insurance, route permit, radio licence, carbon tax) is
 * its own row with independent expiry — never a single combined
 * "roadworthy" flag. `renewal_wo_id` is a real FK to `OPS-02`'s
 * `work_orders`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vehicle_compliance', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('vehicle_id')->constrained('vehicles');
            $table->string('compliance_type', 40);
            $table->string('reference_number', 60)->nullable();
            $table->date('issued_on')->nullable();
            $table->date('expires_on');
            $table->bigInteger('cost_minor')->nullable();
            $table->char('currency', 3)->nullable();
            $table->unsignedBigInteger('document_file_id')->nullable();
            $table->string('issuing_authority', 120)->nullable();
            $table->string('status', 20);
            $table->foreignId('renewal_wo_id')->nullable()->constrained('work_orders');

            $table->index(['school_id', 'vehicle_id', 'compliance_type'], 'vehicle_compliance_type_idx');
            $table->index(['school_id', 'expires_on', 'status'], 'vehicle_compliance_expiry_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vehicle_compliance');
    }
};
