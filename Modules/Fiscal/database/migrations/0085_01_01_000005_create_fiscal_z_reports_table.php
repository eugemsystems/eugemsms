<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book H3 FIN-13 §3/BR-FIN-13-018.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fiscal_z_reports', function (Blueprint $table): void {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('device_id')->constrained('fiscal_devices');
            $table->foreignId('fiscal_day_id')->unique()->constrained('fiscal_days');
            $table->date('report_date');
            $table->integer('receipt_count');
            $table->json('totals_by_currency');
            $table->json('totals_by_tax_type');
            $table->json('totals_by_payment');
            $table->timestamp('submitted_at')->nullable();
            $table->string('status', 20);
            $table->unsignedBigInteger('document_id')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fiscal_z_reports');
    }
};
