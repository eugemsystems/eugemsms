<?php

declare(strict_types=1);

namespace Modules\Sport\Domain\Listeners;

use Illuminate\Support\Carbon;
use Modules\Core\Domain\Support\Settings\ScopeChain;
use Modules\Core\Domain\Support\Settings\SettingResolver;
use Modules\People\Models\Student;
use Modules\Sport\Models\HousePoint;
use Modules\Welfare\Domain\Events\BehaviourRecorded;
use Modules\Welfare\Domain\Events\PositiveBehaviourRecorded;
use Modules\Welfare\Models\BehaviourRecord;

/**
 * Book H2 OPS-07 §3/BR-OPS-07-008 — a real, additive listener on
 * `Modules\Welfare`'s already-shipped `BehaviourRecorded`/
 * `PositiveBehaviourRecorded` events (Book G BRD-07), not an edit to
 * `RecordBehaviourAction` itself. Silently no-ops for a student with
 * no `house_id` (not every school assigns one) — that is not an
 * error, just nothing to attribute the points to.
 */
final class RecordHousePointsFromBehaviourListener
{
    public function __construct(
        private readonly SettingResolver $settings,
    ) {}

    public function handle(BehaviourRecorded|PositiveBehaviourRecorded $event): void
    {
        $record = $event->record;
        $student = Student::find($record->student_id);

        if ($student === null || $student->house_id === null) {
            return;
        }

        $weight = (float) $this->settings->get('sport.behaviour_house_points_weight', new ScopeChain(schoolId: $record->school_id));
        $signedPoints = $record->polarity === 'negative' ? -abs((float) $record->points) : abs((float) $record->points);

        HousePoint::create([
            'school_id' => $record->school_id,
            'academic_year_id' => $record->academic_year_id,
            'term_id' => $record->term_id,
            'house_id' => $student->house_id,
            'source_type' => 'behaviour',
            'source_id' => $record->id,
            'points' => $signedPoints * $weight,
            'reason' => $this->reasonFor($record),
            'awarded_at' => Carbon::now(),
        ]);
    }

    private function reasonFor(BehaviourRecord $record): string
    {
        return "Behaviour record #{$record->id}";
    }
}
