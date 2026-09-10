<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Actions\Documents;

use Illuminate\Support\Carbon;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\DataObjects\Documents\VoidAllocatedNumberData;
use Modules\Core\Domain\Events\Documents\NumberVoided;
use Modules\Core\Domain\Exceptions\ReasonRequiredException;
use Modules\Core\Models\AllocatedNumber;

/**
 * ACT-VoidAllocatedNumber (Book A CORE-06 BR-CORE-06-002). The number
 * itself is never reissued or deleted — it's marked voided with a
 * mandatory reason, which is what the gap report (BR-CORE-06-006)
 * reads back.
 */
final class VoidAllocatedNumberAction extends Action
{
    public function execute(VoidAllocatedNumberData $data): AllocatedNumber
    {
        if (trim($data->reason) === '') {
            throw new ReasonRequiredException('A reason is required to void an allocated number.');
        }

        $number = AllocatedNumber::withoutGlobalScopes()->findOrFail($data->allocatedNumberId);

        return $this->transaction(function () use ($number, $data): AllocatedNumber {
            $number->forceFill([
                'status' => 'voided',
                'void_reason' => $data->reason,
                'voided_at' => Carbon::now(),
            ])->save();

            event(new NumberVoided($number));

            return $number;
        });
    }
}
