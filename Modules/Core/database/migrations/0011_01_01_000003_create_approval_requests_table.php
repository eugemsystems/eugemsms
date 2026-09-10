<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book A CORE-07 §2/BR-CORE-07-013. Session-bound but never locked out
 * of view: a request created in a term that later locks remains
 * viewable with its history intact — this table is not guarded by
 * `PeriodGuard`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('approval_requests', function (Blueprint $table): void {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('academic_year_id')->constrained();
            $table->foreignId('term_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('chain_id')->constrained('approval_chains');
            $table->string('approvable_type');
            $table->unsignedBigInteger('approvable_id');
            $table->smallInteger('current_step')->default(1);
            $table->string('status', 20)->default('pending');
            $table->string('title', 200);
            $table->text('summary')->nullable();
            $table->bigInteger('amount_minor')->nullable();
            $table->char('amount_currency', 3)->nullable();
            $table->foreignId('requested_by')->constrained('users');
            $table->timestamp('requested_at');
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('due_at')->nullable();

            $table->index(['school_id', 'status', 'current_step']);
            $table->index(['approvable_type', 'approvable_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('approval_requests');
    }
};
