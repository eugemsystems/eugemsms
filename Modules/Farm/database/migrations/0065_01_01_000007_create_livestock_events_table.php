<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book H2 OPS-03 §2 ⭐/BR-OPS-03-012/013/014 — APPEND-ONLY.
 * `withdrawal_ends_on` is the hard food-safety block
 * `TransferToKitchenAction` checks before any milk/meat transfer.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('livestock_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('livestock_id')->constrained('livestock');
            $table->string('event_type', 30);
            $table->date('event_date');
            $table->integer('head_count_affected')->default(1);
            $table->string('description', 500)->nullable();
            $table->string('medication', 150)->nullable();
            $table->string('dosage', 60)->nullable();
            $table->smallInteger('withdrawal_period_days')->nullable();
            $table->date('withdrawal_ends_on')->nullable();
            $table->decimal('weight_kg', 8, 2)->nullable();
            $table->bigInteger('cost_minor')->nullable();
            $table->string('performed_by', 150)->nullable();
            $table->foreignId('recorded_by')->constrained('users');

            $table->index(['school_id', 'livestock_id', 'event_date'], 'livestock_events_livestock_date_idx');
            $table->index(['school_id', 'event_type', 'event_date'], 'livestock_events_type_date_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('livestock_events');
    }
};
