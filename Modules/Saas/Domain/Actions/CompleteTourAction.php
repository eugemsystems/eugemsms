<?php

declare(strict_types=1);

namespace Modules\Saas\Domain\Actions;

use Illuminate\Support\Carbon;
use Modules\Core\Domain\Actions\Action;
use Modules\Saas\Models\ProductTour;
use Modules\Saas\Models\TourCompletion;

/**
 * ACT-CompleteTour (Book J SAA-03 §2/§4/BR-SAA-03-005).
 */
final class CompleteTourAction extends Action
{
    public function execute(int $userId, string $tourKey): TourCompletion
    {
        ProductTour::query()->where('key', $tourKey)->firstOrFail();

        return $this->transaction(fn (): TourCompletion => TourCompletion::updateOrCreate(
            ['user_id' => $userId, 'tour_key' => $tourKey],
            ['completed_at' => Carbon::now(), 'skipped_at' => null],
        ));
    }
}
