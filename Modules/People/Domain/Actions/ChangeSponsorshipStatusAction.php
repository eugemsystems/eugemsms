<?php

declare(strict_types=1);

namespace Modules\People\Domain\Actions;

use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\Exceptions\InvalidStateTransitionException;
use Modules\People\Domain\DataObjects\ChangeSponsorshipStatusData;
use Modules\People\Models\Sponsorship;

/**
 * ACT-ChangeSponsorshipStatus (Book C PPL-03 §3). draft -> active <-> suspended
 * -> completed. A completed sponsorship is final.
 */
final class ChangeSponsorshipStatusAction extends Action
{
    private const TRANSITIONS = [
        'draft' => ['active'],
        'active' => ['suspended', 'completed'],
        'suspended' => ['active', 'completed'],
        'completed' => [],
    ];

    public function execute(ChangeSponsorshipStatusData $data): Sponsorship
    {
        $sponsorship = Sponsorship::findOrFail($data->sponsorshipId);

        if (! in_array($data->newStatus, self::TRANSITIONS[$sponsorship->status] ?? [], true)) {
            throw new InvalidStateTransitionException(
                "Sponsorship #{$sponsorship->id} cannot go from [{$sponsorship->status}] to [{$data->newStatus}].",
                ['sponsorship_id' => $sponsorship->id, 'from' => $sponsorship->status, 'to' => $data->newStatus],
            );
        }

        return $this->transaction(function () use ($sponsorship, $data): Sponsorship {
            $sponsorship->update(['status' => $data->newStatus]);

            return $sponsorship->fresh();
        });
    }
}
