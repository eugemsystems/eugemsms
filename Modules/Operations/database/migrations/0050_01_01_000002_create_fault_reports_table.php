<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book H2 OPS-02 §2/BR-OPS-02-001/002 ⭐ — low friction by design: any
 * authenticated user may raise one with only a location, description
 * and severity.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fault_reports', function (Blueprint $table): void {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('term_id')->constrained();
            $table->string('report_number', 40);
            $table->foreignId('maintenance_asset_id')->nullable()->constrained('maintenance_assets');
            $table->string('location', 150);
            $table->string('category', 40);
            $table->text('description');
            $table->json('photo_file_ids')->nullable();
            $table->string('severity', 20);
            $table->boolean('affects_safety')->default(false);
            $table->boolean('affects_teaching')->default(false);
            $table->foreignId('reported_by')->constrained('users');
            $table->timestamp('reported_at');
            $table->string('source_type', 40)->nullable();
            $table->unsignedBigInteger('source_id')->nullable();
            $table->string('status', 20);
            $table->unsignedBigInteger('work_order_id')->nullable();
            $table->foreignId('triaged_by')->nullable()->constrained('users');
            $table->string('triage_note', 255)->nullable();

            $table->unique(['school_id', 'report_number']);
            $table->index(['school_id', 'status', 'severity'], 'fault_reports_status_severity_idx');
            $table->index(['school_id', 'affects_safety', 'status'], 'fault_reports_safety_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fault_reports');
    }
};
