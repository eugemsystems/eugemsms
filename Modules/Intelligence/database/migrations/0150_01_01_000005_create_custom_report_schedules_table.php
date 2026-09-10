<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book J INT-01 §2/BR-INT-01-010. Named `custom_report_schedules`, not
 * the spec's literal `report_schedules` — that name is already taken
 * by `Modules\Reporting\Models\ReportSchedule` (Book H3 FIN-12), a
 * different table for FIN-12's own pre-built statements, not this
 * module's ad hoc reports. Same rename discipline already used for
 * `messaging_gateway_webhooks` (COM-01) avoiding FIN-05's
 * `gateway_webhooks`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('custom_report_schedules', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('report_id')->constrained('custom_reports')->cascadeOnDelete();
            $table->string('frequency', 20);
            $table->json('recipients');
            $table->string('format', 20);
            $table->timestamp('next_run_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('custom_report_schedules');
    }
};
