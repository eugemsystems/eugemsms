<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book H1 FIN-09 §2. `approval_request_id` is a forward reference to
 * `CORE-07`, no FK yet.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('store_requisitions', function (Blueprint $table): void {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('academic_year_id')->constrained();
            $table->foreignId('term_id')->constrained();
            $table->string('requisition_number', 40);
            $table->foreignId('store_id')->constrained('stores');
            $table->foreignId('requesting_department_id')->nullable()->constrained('departments');
            $table->foreignId('cost_centre_id')->constrained('cost_centres');
            $table->string('purpose', 255);
            $table->date('required_by')->nullable();
            $table->string('source_type', 40)->nullable();
            $table->unsignedBigInteger('source_id')->nullable();
            $table->string('status', 20);
            $table->unsignedBigInteger('approval_request_id')->nullable();
            $table->foreignId('requested_by')->constrained('users');
            $table->foreignId('approved_by')->nullable()->constrained('users');
            $table->foreignId('issued_by')->nullable()->constrained('users');
            $table->timestamp('issued_at')->nullable();
            $table->foreignId('received_by')->nullable()->constrained('users');
            $table->bigInteger('total_cost_minor')->nullable();
            $table->string('currency', 3);
            $table->foreignId('journal_id')->nullable()->constrained('journals');
            $table->timestamps();

            $table->unique(['school_id', 'requisition_number']);
            $table->index(['school_id', 'store_id', 'status'], 'store_requisitions_store_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('store_requisitions');
    }
};
