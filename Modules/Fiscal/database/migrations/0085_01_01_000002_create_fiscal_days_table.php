<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book H3 FIN-13 §5/BR-FIN-13-007. `local_status` and `fdms_status`
 * are deliberately separate columns — a close can be initiated and
 * not yet confirmed, and collapsing them into one produces counter
 * mismatches that are painful to unwind.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fiscal_days', function (Blueprint $table): void {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('device_id')->constrained('fiscal_devices');
            $table->integer('fiscal_day_number');
            $table->timestamp('opened_at');
            $table->foreignId('opened_by')->constrained('users');
            $table->timestamp('closed_at')->nullable();
            $table->foreignId('closed_by')->nullable()->constrained('users');
            $table->string('local_status', 20);
            $table->string('fdms_status', 30)->nullable();
            $table->integer('receipt_count')->default(0);
            $table->json('counters')->nullable();
            $table->string('day_hash', 120)->nullable();
            $table->text('day_signature')->nullable();
            $table->smallInteger('close_attempts')->default(0);
            $table->text('close_error')->nullable();

            $table->unique(['school_id', 'device_id', 'fiscal_day_number'], 'fiscal_days_school_device_number_unique');
            $table->index(['school_id', 'local_status'], 'fiscal_days_school_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fiscal_days');
    }
};
