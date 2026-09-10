<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book A CORE-13 §2, extended with `requested_by`/`approved_by` (spec is
 * silent on how BR-CORE-13-009's "restores in production require dual
 * authorisation" is data-modelled — a production restore is recorded as
 * a `restore_tests` row with `target_environment = 'production'` and
 * `status` starting at `pending_approval` rather than `running`, going
 * through the same requester-then-different-approver shape CORE-03's
 * `period_reopen_requests` already uses, rather than inventing a
 * separate table for one extra business rule).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('restore_tests', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('backup_id')->constrained()->cascadeOnDelete();
            $table->string('status', 20);
            $table->string('target_environment', 60);
            $table->json('checks_performed')->nullable();
            $table->integer('duration_seconds')->nullable();
            $table->text('error')->nullable();
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('tested_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('restore_tests');
    }
};
