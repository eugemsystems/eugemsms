<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book K PPL-06 §2/BR-PPL-06-005. Built entirely on `COM-06`'s own
 * calendar — `calendar_event_id` is created through Comms'
 * `CreateCalendarEventAction` first; this table adds only
 * `target_graduation_years` targeting on top of it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('alumni_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('calendar_event_id')->constrained('calendar_events');
            $table->string('event_type', 30);
            $table->json('target_graduation_years')->nullable();
            $table->boolean('requires_ticket')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('alumni_events');
    }
};
