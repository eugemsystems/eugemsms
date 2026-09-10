<?php

declare(strict_types=1);

namespace Modules\Sport\Domain\Actions;

use Illuminate\Support\Carbon;
use Modules\Core\Domain\Actions\Action;
use Modules\Sport\Models\ActivityMembership;

/**
 * ACT-WithdrawFromActivity (Book H2 OPS-07 §2).
 */
final class WithdrawFromActivityAction extends Action
{
    public function execute(int $membershipId): ActivityMembership
    {
        $membership = ActivityMembership::findOrFail($membershipId);

        return $this->transaction(fn (): ActivityMembership => tap($membership)->update([
            'status' => 'withdrawn',
            'left_on' => Carbon::now()->toDateString(),
        ]));
    }
}
