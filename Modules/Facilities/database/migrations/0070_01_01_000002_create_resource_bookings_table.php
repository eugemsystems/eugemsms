<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book H2 OPS-05 §2/BR-OPS-05-001/002/007. `setup_work_order_id`/
 * `cleanup_work_order_id` are real FKs into `OPS-02`'s `work_orders`
 * (already exists). `invoice_id`/`deposit_receipt_id` reference
 * `FIN-03`/`FIN-04` tables that exist but whose own actions don't fit
 * a non-learner external hirer (see this module's own provider
 * docblock) — real FKs kept for future use, populated by neither
 * action in this pass.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('resource_bookings', function (Blueprint $table): void {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('term_id')->constrained();
            $table->string('booking_number', 40);
            $table->foreignId('resource_id')->constrained('bookable_resources');
            $table->string('booking_type', 20);
            $table->string('purpose', 255);
            $table->timestamp('starts_at');
            $table->timestamp('ends_at');
            $table->timestamp('setup_from')->nullable();
            $table->timestamp('cleanup_until')->nullable();
            $table->smallInteger('expected_attendance')->nullable();
            $table->foreignId('requested_by_staff_id')->nullable()->constrained('staff');
            $table->foreignId('department_id')->nullable()->constrained('departments');
            $table->string('hirer_name', 200)->nullable();
            $table->string('hirer_contact', 80)->nullable();
            $table->string('hirer_organisation', 200)->nullable();
            $table->bigInteger('hire_amount_minor')->nullable();
            $table->bigInteger('deposit_amount_minor')->nullable();
            $table->foreignId('deposit_receipt_id')->nullable()->constrained('receipts');
            $table->foreignId('invoice_id')->nullable()->constrained('invoices');
            $table->unsignedBigInteger('contract_file_id')->nullable();
            $table->boolean('deposit_refunded')->nullable();
            $table->bigInteger('damage_deducted_minor')->nullable();
            $table->string('status', 20);
            $table->string('cancellation_reason', 255)->nullable();
            $table->unsignedBigInteger('approval_request_id')->nullable();
            $table->string('recurrence_rule', 120)->nullable();
            $table->foreignId('parent_booking_id')->nullable()->constrained('resource_bookings');
            $table->foreignId('setup_work_order_id')->nullable()->constrained('work_orders');
            $table->foreignId('cleanup_work_order_id')->nullable()->constrained('work_orders');
            $table->text('condition_before_notes')->nullable();
            $table->text('condition_after_notes')->nullable();

            $table->unique(['school_id', 'booking_number'], 'resource_bookings_school_number_unique');
            $table->index(['school_id', 'resource_id', 'starts_at', 'ends_at'], 'resource_bookings_resource_window_idx');
            $table->index(['school_id', 'status', 'starts_at'], 'resource_bookings_status_starts_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('resource_bookings');
    }
};
