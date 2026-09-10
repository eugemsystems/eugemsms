<?php

declare(strict_types=1);

namespace Modules\Stores\Domain\Actions;

use Illuminate\Support\Carbon;
use Modules\Core\Domain\Actions\Action;
use Modules\Stores\Domain\DataObjects\CreateForecastData;
use Modules\Stores\Domain\Events\ForecastGenerated;
use Modules\Stores\Models\Forecast;

/**
 * ACT-CreateForecast (Book H1 FIN-11 §6/BR-FIN-11-013/014/015 ⭐/
 * AC-FIN-11-006). Never touches `budgets`/`budget_lines` — the
 * projection math itself (enrolment-driven fee income, cash-flow
 * combination of receipts/expenditure/payroll) is a caller-supplied
 * `projections` payload rather than computed here; this action's own
 * job is only ever to store a scenario as a scenario, never silently
 * promote one to a plan. Only one baseline is kept per (year, type) —
 * a new baseline demotes the previous one rather than leaving two.
 */
final class CreateForecastAction extends Action
{
    public function execute(CreateForecastData $data): Forecast
    {
        return $this->transaction(function () use ($data): Forecast {
            if ($data->isBaseline) {
                Forecast::where('school_id', $data->schoolId)
                    ->where('academic_year_id', $data->academicYearId)
                    ->where('forecast_type', $data->forecastType)
                    ->where('is_baseline', true)
                    ->update(['is_baseline' => false]);
            }

            $forecast = Forecast::create([
                'school_id' => $data->schoolId,
                'academic_year_id' => $data->academicYearId,
                'forecast_type' => $data->forecastType,
                'scenario_name' => $data->scenarioName,
                'assumptions' => $data->assumptions,
                'projections' => $data->projections,
                'generated_at' => Carbon::now(),
                'generated_by' => $data->generatedByUserId,
                'is_baseline' => $data->isBaseline,
            ]);

            event(new ForecastGenerated($forecast));

            return $forecast;
        });
    }
}
