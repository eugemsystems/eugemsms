<?php

declare(strict_types=1);

namespace Modules\Finance\Console\Tasks;

use Modules\Core\Domain\Actions\Scheduling\ResolveSystemActorAction;
use Modules\Core\Domain\Contracts\ScheduledTaskHandler;
use Modules\Core\Models\School;
use Modules\Finance\Domain\Actions\PollPendingIntentAction;
use Modules\Finance\Domain\DataObjects\PollPendingIntentData;
use Modules\Finance\Models\PaymentIntent;

/**
 * Scheduled (FIN-05 BR-FIN-05-007/008): polls gateways for payment intents whose webhook never arrived, so a parent who paid always ends up receipted.
 */
final class PollPendingPaymentIntentsTask implements ScheduledTaskHandler
{
    public function handle(School $school): string
    {
        $actor = app(ResolveSystemActorAction::class)->execute();
        $count = 0;

        $ids = PaymentIntent::query()
            ->whereIn('status', ['created', 'pending', 'processing'])
            ->where('created_at', '<', now()->subMinutes(2))
            ->where('created_at', '>', now()->subDays(3))
            ->pluck('id');

        foreach ($ids as $intentId) {
            app(PollPendingIntentAction::class)->execute(new PollPendingIntentData((int) $intentId, $actor));
            $count++;
        }

        return "{$count} intent(s) polled";
    }
}
