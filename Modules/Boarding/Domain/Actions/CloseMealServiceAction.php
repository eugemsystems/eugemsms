<?php

declare(strict_types=1);

namespace Modules\Boarding\Domain\Actions;

use Modules\Boarding\Domain\DataObjects\CloseMealServiceData;
use Modules\Boarding\Domain\Events\ServiceClosed;
use Modules\Boarding\Models\MealRequisitionLine;
use Modules\Boarding\Models\MealService;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\Exceptions\InvalidStateTransitionException;

/**
 * ACT-CloseMealService (Book F BRD-04 §4/BR-BRD-04-012/017). A
 * service cannot close without `actual_served` recorded, so cost per
 * serving is always computable — though in planning-only mode
 * (`StoreIssuanceProvider` stubbed, no `issued_cost_minor`)
 * `cost_per_serving_minor` stays `null`, never `0`.
 */
final class CloseMealServiceAction extends Action
{
    public function execute(CloseMealServiceData $data): MealService
    {
        $service = MealService::findOrFail($data->mealServiceId);

        if ($service->status === 'closed') {
            throw new InvalidStateTransitionException(
                "Meal service #{$service->id} is already closed.",
                ['meal_service_id' => $service->id],
            );
        }

        // The cost of what was actually served: the planned ingredient cost scaled to the servings
        // that went out. Only when every ingredient is priced — a partly costed meal has no cost.
        $lines = MealRequisitionLine::query()->where('meal_service_id', $service->id)->get(['line_cost_minor']);
        $fullyCosted = $lines->isNotEmpty() && $lines->every(fn (MealRequisitionLine $line): bool => $line->line_cost_minor !== null);
        $issuedCost = $fullyCosted && $service->planned_servings > 0
            ? (int) round($lines->sum('line_cost_minor') * min(1.0, $data->actualServed / $service->planned_servings))
            : null;
        $costPerServing = $issuedCost !== null && $data->actualServed > 0 ? (int) round($issuedCost / $data->actualServed) : null;

        return $this->transaction(function () use ($service, $data, $costPerServing, $issuedCost): MealService {
            $service->update([
                'actual_served' => $data->actualServed,
                'wastage_note' => $data->wastageNote,
                'issued_cost_minor' => $issuedCost,
                'cost_per_serving_minor' => $costPerServing,
                'status' => 'closed',
            ]);

            event(new ServiceClosed($service));

            return $service;
        });
    }
}
