<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book H2 OPS-07 §2. `house_id` is a real FK into `Core\Models\House`
 * (Book A CORE-02) — the same house a student's own `house_id` points
 * to, not `Modules\Boarding`'s `Hostel`. `source_type`/`source_id`
 * is a plain polymorphic pair (not a dedicated FK per source) because
 * sources span four different modules: this one (`manual`,
 * `competition` via `house_competitions`), `Modules\Welfare`
 * (`behaviour` via `behaviour_records`, listened to off the real
 * `BehaviourRecorded`/`PositiveBehaviourRecorded` events) and
 * `Modules\Boarding` (`inspection` via `room_inspections`, listened to
 * off the real `InspectionRecorded` event) — see
 * `RecordHousePointsFromBehaviourListener`/
 * `RecordHousePointsFromInspectionListener` and the owning provider's
 * own docblock. `academic` sourcing (BR-OPS-07-008's fourth source) is
 * a documented deferral — no module fires an equivalent "academic
 * result posted" domain event yet to listen for.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('house_points', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('academic_year_id')->constrained();
            $table->foreignId('term_id')->constrained();
            $table->foreignId('house_id')->constrained('houses');
            $table->foreignId('competition_id')->nullable()->constrained('house_competitions');
            $table->string('source_type', 30);
            $table->unsignedBigInteger('source_id')->nullable();
            $table->decimal('points', 8, 2);
            $table->string('reason', 255)->nullable();
            $table->timestamp('awarded_at');
            $table->foreignId('awarded_by')->nullable()->constrained('users');

            $table->index(['school_id', 'academic_year_id', 'house_id'], 'house_points_school_year_house_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('house_points');
    }
};
