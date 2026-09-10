<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book I COM-07 §2 ⭐/BR-COM-07-005 (AC-COM-07-004). The unique index
 * is the STRUCTURAL double-booking guard the acceptance criterion
 * asks for — two simultaneous booking attempts for the same slot both
 * try to insert `status = 'booked'` at that `slot_starts_at`, and the
 * DB itself rejects the second, not an application-level check that
 * could race.
 *
 * One documented deviation from the spec's literal 2-column
 * `UNIQUE(window_id, slot_starts_at)`: `status` is a THIRD column in
 * this index. A 2-column unique key would make a cancelled booking
 * permanently occupy its slot — "releases the slot back to the
 * booking pool" (`BR-COM-07-005`) requires a NEW row to be insertable
 * at the same `(window_id, slot_starts_at)` once the old one is
 * `cancelled`, which only a 3-column key allows while still blocking
 * two simultaneous `booked` rows at the same slot (the actual
 * property being protected).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('consultation_bookings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('window_id')->constrained('consultation_windows')->cascadeOnDelete();
            $table->foreignId('guardian_id')->constrained('guardians');
            $table->foreignId('student_id')->constrained();
            $table->timestamp('slot_starts_at');
            $table->foreignId('meeting_id')->nullable()->constrained('scheduled_meetings');
            $table->string('status', 20);
            $table->timestamps();

            $table->unique(['window_id', 'slot_starts_at', 'status'], 'consultation_bookings_slot_status_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('consultation_bookings');
    }
};
