<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book H2 OPS-04 §2/BR-OPS-04-006 — APPEND-ONLY. A correction is a
 * new reading with a note, never an edit, guarded at the model level
 * the same way `Modules\Stores\Models\StockMovement` guards its own
 * append-only table.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('meter_readings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('meter_id')->constrained('meters');
            $table->date('read_on');
            $table->decimal('reading', 14, 3);
            $table->decimal('previous_reading', 14, 3)->nullable();
            $table->decimal('consumption', 14, 3)->nullable();
            $table->smallInteger('days_since_last')->nullable();
            $table->decimal('daily_average', 12, 3)->nullable();
            $table->string('reading_method', 20);
            $table->unsignedBigInteger('photo_file_id')->nullable();
            $table->foreignId('read_by')->constrained('users');
            $table->boolean('is_anomaly')->default(false);
            $table->text('anomaly_note')->nullable();

            $table->unique(['meter_id', 'read_on'], 'meter_readings_meter_date_unique');
            $table->index(['school_id', 'read_on'], 'meter_readings_school_date_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meter_readings');
    }
};
