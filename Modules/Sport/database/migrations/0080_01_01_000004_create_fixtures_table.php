<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book H2 OPS-07 §2. `trip_id` (`OPS-01`, `Modules\Transport`) and
 * `booking_id` (`OPS-05`, `Modules\Facilities`) are both real FKs —
 * `ConfirmFixtureAction` populates whichever one applies via
 * `ScheduleTripAction`/`RequestBookingAction`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fixtures', function (Blueprint $table): void {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('term_id')->constrained();
            $table->foreignId('team_id')->constrained();
            $table->string('opponent', 200);
            $table->string('fixture_type', 20);
            $table->string('venue_type', 10);
            $table->foreignId('venue_id')->nullable()->constrained('venues');
            $table->string('venue_name', 200)->nullable();
            $table->date('fixture_date');
            $table->time('start_time')->nullable();
            $table->time('departure_time')->nullable();
            $table->time('return_time')->nullable();
            $table->foreignId('trip_id')->nullable()->constrained('trips');
            $table->foreignId('booking_id')->nullable()->constrained('resource_bookings');
            $table->json('squad_student_ids')->nullable();
            $table->json('staff_ids')->nullable();
            $table->string('result', 20)->nullable();
            $table->string('score_for', 30)->nullable();
            $table->string('score_against', 30)->nullable();
            $table->text('match_report')->nullable();
            $table->string('status', 20);
            $table->timestamp('guardians_notified_at')->nullable();

            $table->index(['school_id', 'fixture_date', 'status'], 'fixtures_school_date_status_idx');
            $table->index(['school_id', 'team_id', 'fixture_date'], 'fixtures_school_team_date_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fixtures');
    }
};
