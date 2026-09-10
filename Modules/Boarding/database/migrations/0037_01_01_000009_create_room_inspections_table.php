<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book F BRD-01 §2/BR-BRD-01-011. `photo_file_ids` is a forward
 * reference to `CORE-10` (no FK yet, matching the session's own
 * established precedent for file references).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('room_inspections', function (Blueprint $table): void {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('term_id')->constrained();
            $table->foreignId('room_id')->constrained('hostel_rooms');
            $table->date('inspection_date');
            $table->string('inspection_type', 20);
            $table->json('criteria_scores');
            $table->decimal('total_score', 5, 2)->nullable();
            $table->decimal('max_score', 5, 2);
            $table->string('grade', 20)->nullable();
            $table->text('findings')->nullable();
            $table->json('photo_file_ids')->nullable();
            $table->foreignId('inspector_staff_id')->constrained('staff');
            $table->boolean('follow_up_required')->default(false);
            $table->date('follow_up_by')->nullable();

            $table->index(['school_id', 'room_id', 'inspection_date'], 'room_inspections_room_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('room_inspections');
    }
};
