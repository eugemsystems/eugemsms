<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book I COM-07 §2 ⭐/BR-COM-07-001/002. `timetable_slot_id` is a
 * plain nullable FK into `Modules\Academic`'s already-real
 * `timetable_slots` table — cross-module hard FK to an EXISTING
 * table, the same pattern `Modules\Sport`'s `activity_memberships`
 * already uses for `ad_hoc_charge_id` into Finance. This module never
 * writes a "flagged for virtual delivery" column onto `timetable_slots`
 * itself (Academic's own table, out of this book's authority to
 * extend) — the mere EXISTENCE of a `scheduled_meetings` row with
 * `meeting_type = 'online_lesson'` for a given slot IS the flag,
 * queried directly rather than stored redundantly on the other side.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('scheduled_meetings', function (Blueprint $table): void {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('term_id')->constrained();
            $table->string('meeting_type', 20);
            $table->foreignId('provider_id')->constrained('meeting_providers');
            $table->foreignId('timetable_slot_id')->nullable()->constrained('timetable_slots');
            $table->string('provider_meeting_id', 80)->nullable();
            $table->string('join_url', 500)->nullable();
            $table->text('host_url')->nullable();
            $table->text('passcode')->nullable();
            $table->timestamp('starts_at');
            $table->smallInteger('duration_minutes');
            $table->foreignId('host_staff_id')->nullable()->constrained('staff');
            $table->boolean('waiting_room_enabled')->default(true);
            $table->boolean('recording_enabled')->default(false);
            $table->string('recording_url', 500)->nullable();
            $table->date('recording_expires_on')->nullable();
            $table->string('status', 20);
            $table->timestamps();

            $table->index(['school_id', 'starts_at', 'status']);
            $table->index(['school_id', 'timetable_slot_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scheduled_meetings');
    }
};
