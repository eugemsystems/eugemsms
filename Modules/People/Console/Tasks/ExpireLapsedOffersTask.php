<?php

declare(strict_types=1);

namespace Modules\People\Console\Tasks;

use Modules\Core\Domain\Contracts\ScheduledTaskHandler;
use Modules\Core\Models\School;
use Modules\People\Domain\Actions\ExpireOfferAction;
use Modules\People\Domain\DataObjects\ExpireOfferData;
use Modules\People\Models\Application;

/**
 * Scheduled (PPL-02): lapses every offer past its expiry and returns the place to the waitlist.
 */
final class ExpireLapsedOffersTask implements ScheduledTaskHandler
{
    public function handle(School $school): string
    {
        $count = 0;

        foreach (Application::query()->where('status', 'offered')->whereNotNull('offer_expires_at')->where('offer_expires_at', '<', now())->pluck('id') as $applicationId) {
            app(ExpireOfferAction::class)->execute(new ExpireOfferData((int) $applicationId));
            $count++;
        }

        return "{$count} offer(s) expired";
    }
}
