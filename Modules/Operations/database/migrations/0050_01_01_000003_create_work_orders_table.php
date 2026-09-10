<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book H2 OPS-02 §2/BR-OPS-02-004/008. `budget_line_id` is a real FK
 * into `FIN-11`'s `budget_lines` (Book H1, already built) — a work
 * order above the configured value checks real budget availability
 * the same way `FIN-08`'s own requisition does.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('work_orders', function (Blueprint $table): void {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('academic_year_id')->constrained();
            $table->foreignId('term_id')->constrained();
            $table->string('work_order_number', 40);
            $table->foreignId('maintenance_asset_id')->nullable()->constrained('maintenance_assets');
            $table->foreignId('fault_report_id')->nullable()->constrained('fault_reports');
            $table->unsignedBigInteger('schedule_id')->nullable();
            $table->string('work_type', 20);
            $table->string('title', 200);
            $table->text('description');
            $table->string('location', 150)->nullable();
            $table->string('priority', 20);
            $table->string('assigned_team', 20);
            $table->foreignId('assigned_staff_id')->nullable()->constrained('staff');
            $table->foreignId('contractor_supplier_id')->nullable()->constrained('suppliers');
            $table->foreignId('cost_centre_id')->constrained('cost_centres');
            $table->foreignId('budget_line_id')->nullable()->constrained('budget_lines');
            $table->date('scheduled_for')->nullable();
            $table->date('target_completion')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->string('status', 20);
            $table->unsignedBigInteger('approval_request_id')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users');
            $table->decimal('labour_hours', 6, 2)->default(0);
            $table->bigInteger('labour_cost_minor')->default(0);
            $table->bigInteger('parts_cost_minor')->default(0);
            $table->bigInteger('contractor_cost_minor')->default(0);
            $table->bigInteger('total_cost_minor')->default(0);
            $table->string('currency', 3);
            $table->text('completion_notes')->nullable();
            $table->json('completion_photo_ids')->nullable();
            $table->foreignId('verified_by')->nullable()->constrained('users');
            $table->timestamp('verified_at')->nullable();
            $table->boolean('sla_met')->nullable();
            $table->foreignId('raised_by')->constrained('users');

            $table->unique(['school_id', 'work_order_number']);
            $table->index(['school_id', 'status', 'priority'], 'work_orders_status_priority_idx');
            $table->index(['school_id', 'maintenance_asset_id'], 'work_orders_asset_idx');
            $table->index(['school_id', 'target_completion', 'status'], 'work_orders_target_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('work_orders');
    }
};
