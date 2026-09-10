<?php

declare(strict_types=1);

namespace Modules\Farm\Domain\Exceptions;

use Illuminate\Support\Carbon;
use Modules\Core\Domain\Exceptions\DomainException;

/**
 * Book H2 OPS-03 §4 ⭐⭐/BR-OPS-03-012/AC-OPS-03-003. A hard,
 * food-safety block — never bypassable by permission or override. The
 * message always names the withdrawal period and its end date.
 */
class WithdrawalPeriodActiveException extends DomainException
{
    public static function forLivestock(int $livestockId, Carbon $withdrawalEndsOn): self
    {
        return new self(
            "Livestock #{$livestockId} is within its withdrawal period, ending {$withdrawalEndsOn->toDateString()} — milk or meat cannot be transferred to the kitchen until then (BR-OPS-03-012).",
            ['livestock_id' => $livestockId, 'withdrawal_ends_on' => $withdrawalEndsOn->toDateString()],
        );
    }

    public function errorCode(): string
    {
        return 'WITHDRAWAL_PERIOD_ACTIVE';
    }
}
