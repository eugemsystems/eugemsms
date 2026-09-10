<?php

declare(strict_types=1);

namespace Modules\Boarding\Domain\Actions;

use Modules\Boarding\Domain\DataObjects\SetMenuDayData;
use Modules\Boarding\Models\MenuCycle;
use Modules\Boarding\Models\MenuDay;
use Modules\Core\Domain\Actions\Action;

/**
 * ACT-SetMenuDay (Book F BRD-04 §2).
 */
final class SetMenuDayAction extends Action
{
    public function execute(SetMenuDayData $data): MenuDay
    {
        $cycle = MenuCycle::findOrFail($data->cycleId);

        return $this->transaction(fn (): MenuDay => MenuDay::updateOrCreate(
            ['cycle_id' => $cycle->id, 'cycle_day' => $data->cycleDay, 'meal' => $data->meal],
            ['school_id' => $cycle->school_id, 'recipe_ids' => $data->recipeIds, 'notes' => $data->notes],
        ));
    }
}
