<?php

declare(strict_types=1);

namespace Modules\Academic\Domain\Actions;

use Modules\Academic\Domain\DataObjects\CreatePeriodStructureData;
use Modules\Academic\Models\PeriodSlot;
use Modules\Academic\Models\PeriodStructure;
use Modules\Core\Domain\Actions\Action;

/**
 * ACT-CreatePeriodStructure (Book E ACA-03 §2/BR-ACA-03-001/003). Takes
 * the structure and its complete slot set together, the same reason
 * `CreateGradingScaleAction` (Book D ACA-05) takes bands together —
 * the day's shape is only meaningful as a whole.
 */
final class CreatePeriodStructureAction extends Action
{
    public function execute(CreatePeriodStructureData $data): PeriodStructure
    {
        return $this->transaction(function () use ($data): PeriodStructure {
            $structure = PeriodStructure::create([
                'school_id' => $data->schoolId,
                'section_id' => $data->sectionId,
                'academic_year_id' => $data->academicYearId,
                'name' => $data->name,
                'cycle_type' => $data->cycleType,
                'cycle_days' => $data->cycleDays,
                'day_labels' => $data->dayLabels,
                'is_default' => $data->isDefault,
                'is_active' => true,
            ]);

            foreach ($data->slots as $index => $slot) {
                PeriodSlot::create([
                    'school_id' => $data->schoolId,
                    'structure_id' => $structure->id,
                    'cycle_day' => $slot->cycleDay,
                    'period_number' => $slot->periodNumber,
                    'label' => $slot->label,
                    'slot_type' => $slot->slotType,
                    'starts_at' => $slot->startsAt,
                    'ends_at' => $slot->endsAt,
                    'duration_minutes' => $slot->durationMinutes,
                    'is_teachable' => $slot->isTeachable,
                    'requires_attendance' => $slot->requiresAttendance,
                    'sort_order' => $index + 1,
                ]);
            }

            return $structure;
        });
    }
}
