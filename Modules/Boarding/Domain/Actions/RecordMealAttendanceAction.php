<?php

declare(strict_types=1);

namespace Modules\Boarding\Domain\Actions;

use Illuminate\Support\Carbon;
use Modules\Boarding\Domain\DataObjects\RecordMealAttendanceData;
use Modules\Boarding\Models\MealAttendance;
use Modules\Boarding\Models\MealService;
use Modules\Core\Domain\Actions\Action;

/**
 * ACT-RecordMealAttendance (Book F BRD-04 §4/BR-BRD-04-015). Optional
 * per school — where disabled, `actual_served` is entered directly
 * by the kitchen manager via `CloseMealServiceAction` instead.
 */
final class RecordMealAttendanceAction extends Action
{
    public function execute(RecordMealAttendanceData $data): MealAttendance
    {
        $service = MealService::findOrFail($data->mealServiceId);

        return $this->transaction(fn (): MealAttendance => MealAttendance::updateOrCreate(
            ['meal_service_id' => $service->id, 'student_id' => $data->studentId],
            [
                'school_id' => $service->school_id,
                'attended' => $data->attended,
                'special_meal_served' => $data->specialMealServed,
                'recorded_at' => Carbon::now(),
                'method' => $data->method,
            ],
        ));
    }
}
