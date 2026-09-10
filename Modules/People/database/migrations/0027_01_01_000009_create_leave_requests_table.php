<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book C PPL-04 §2/§4. `approval_request_id` (CORE-07) and
 * `supporting_document_id` are left unconstrained to their owning
 * tables' rows being created in the same request in this pass — both
 * are plain nullable columns, not yet foreign keys, since CORE-07's
 * approval-chain wiring is deferred along with the rest of this
 * module's screens.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leave_requests', function (Blueprint $table): void {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('staff_id')->constrained('staff')->cascadeOnDelete();
            $table->foreignId('leave_type_id')->constrained('leave_types');
            $table->foreignId('academic_year_id')->constrained();
            $table->date('starts_on');
            $table->date('ends_on');
            $table->decimal('working_days', 5, 1);
            $table->text('reason')->nullable();
            $table->foreignId('supporting_document_id')->nullable()->constrained('documents')->nullOnDelete();
            $table->foreignId('cover_staff_id')->nullable()->constrained('staff')->nullOnDelete();
            $table->string('status', 20);
            $table->unsignedBigInteger('approval_request_id')->nullable();
            $table->string('contact_while_away', 120)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['school_id', 'staff_id', 'starts_on']);
            $table->index(['school_id', 'status', 'starts_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leave_requests');
    }
};
