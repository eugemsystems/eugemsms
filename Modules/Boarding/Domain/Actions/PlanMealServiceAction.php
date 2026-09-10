<?php

declare(strict_types=1);

namespace Modules\Boarding\Domain\Actions;

use Modules\Boarding\Domain\DataObjects\PlanMealServiceData;
use Modules\Boarding\Domain\Events\ServicePlanned;
use Modules\Boarding\Domain\Events\StockShortfall;
use Modules\Boarding\Domain\Support\LiveOccupancyProvider;
use Modules\Boarding\Domain\Support\StoreIssuanceProvider;
use Modules\Boarding\Models\MealRequisitionLine;
use Modules\Boarding\Models\MealService;
use Modules\Boarding\Models\MenuDay;
use Modules\Boarding\Models\RecipeIngredient;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\Support\Settings\ScopeChain;
use Modules\Core\Domain\Support\Settings\SettingResolver;

/**
 * ACT-PlanMealService (Book F BRD-04 §3 ⭐ — "the single feature that
 * pays for the module"). Servings are computed from `BRD-02`'s
 * *live present* occupancy, never allocated beds (BR-BRD-04-001) —
 * a learner on exeat or in the sick bay is excluded from the count
 * (BR-BRD-04-002), and the nominal figure is kept alongside it, never
 * discarded. Requisition quantities scale linearly from each
 * recipe's own `base_servings` with that ingredient's wastage
 * allowance applied (BR-BRD-04-004), and aggregate across every
 * recipe in the service, per inventory item (BR-BRD-04-005).
 * Availability checking degrades honestly in planning-only mode —
 * `StoreIssuanceProvider::checkAvailability()` returning `null`
 * (unknown) never fires a shortfall event; only an explicit `false`
 * does.
 */
final class PlanMealServiceAction extends Action
{
    public function __construct(
        private readonly LiveOccupancyProvider $liveOccupancy,
        private readonly StoreIssuanceProvider $storeIssuance,
        private readonly SettingResolver $settings,
    ) {}

    public function execute(PlanMealServiceData $data): MealService
    {
        $occupancy = $this->liveOccupancy->liveOccupancy($data->serviceDate, $data->meal, $data->hostelId);

        $contingencyPercent = (float) $this->settings->get('catering.contingency_percent', new ScopeChain(schoolId: $data->schoolId));
        $contingency = (int) round($occupancy->present * $contingencyPercent / 100);
        $servings = $occupancy->present + $data->staffMeals + $data->guestMeals + $contingency;

        $lines = $this->computeRequisitionLines($data->menuDayId, $servings);

        return $this->transaction(function () use ($data, $occupancy, $servings, $lines): MealService {
            $service = MealService::updateOrCreate(
                ['school_id' => $data->schoolId, 'service_date' => $data->serviceDate->toDateString(), 'meal' => $data->meal],
                [
                    'term_id' => $data->termId,
                    'menu_day_id' => $data->menuDayId,
                    'nominal_boarders' => $occupancy->allocated,
                    'present_boarders' => $occupancy->present,
                    'on_exeat' => $occupancy->onExeat,
                    'in_sick_bay' => $occupancy->inSickBay,
                    'staff_meals' => $data->staffMeals,
                    'guest_meals' => $data->guestMeals,
                    'planned_servings' => $servings,
                    'currency' => $data->currency,
                    'status' => 'planned',
                ],
            );

            MealRequisitionLine::where('meal_service_id', $service->id)->delete();

            foreach ($lines as $itemId => $line) {
                $requisitionLine = MealRequisitionLine::create([
                    'school_id' => $data->schoolId,
                    'meal_service_id' => $service->id,
                    'inventory_item_id' => $itemId,
                    'required_quantity' => round($line['quantity'], 4),
                    'unit' => $line['unit'],
                ]);

                $available = $this->storeIssuance->checkAvailability($data->schoolId, $itemId, $line['quantity']);

                if ($available === false) {
                    event(new StockShortfall($requisitionLine));
                }
            }

            event(new ServicePlanned($service));

            return $service;
        });
    }

    /**
     * @return array<int, array{quantity: float, unit: string}>
     */
    private function computeRequisitionLines(?int $menuDayId, int $servings): array
    {
        if ($menuDayId === null) {
            return [];
        }

        $menuDay = MenuDay::find($menuDayId);

        if ($menuDay === null) {
            return [];
        }

        $lines = [];

        foreach ($menuDay->recipes() as $recipe) {
            $factor = $recipe->base_servings > 0 ? $servings / $recipe->base_servings : 0.0;

            $ingredients = RecipeIngredient::where('recipe_id', $recipe->id)->get();

            foreach ($ingredients as $ingredient) {
                $quantity = (float) $ingredient->quantity * $factor * (1 + ((float) $ingredient->wastage_allowance_pct / 100));

                if (! isset($lines[$ingredient->inventory_item_id])) {
                    $lines[$ingredient->inventory_item_id] = ['quantity' => 0.0, 'unit' => $ingredient->unit];
                }

                $lines[$ingredient->inventory_item_id]['quantity'] += $quantity;
            }
        }

        return $lines;
    }
}
