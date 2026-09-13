<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book B FIN-03 §2/BR-FIN-03-015. Append-only — `UNIQUE(schedule_id,
 * invoice_id)` is the entire duplicate-suppression mechanism: each
 * ladder rung fires at most once per invoice, ever (AC-FIN-03-005).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reminders_sent', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('schedule_id')->constrained('reminder_schedules')->cascadeOnDelete();
            $table->foreignId('invoice_id')->constrained('invoices')->cascadeOnDelete();
            $table->unsignedBigInteger('notification_id')->nullable();
            $table->bigInteger('balance_at_send_minor');
            $table->timestamp('sent_at');

            $table->unique(['schedule_id', 'invoice_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reminders_sent');
    }
};
