<?php

declare(strict_types=1);

namespace Modules\Boarding\Domain\Actions;

use Modules\Boarding\Domain\DataObjects\RejectExeatData;
use Modules\Boarding\Domain\Events\ExeatRejected;
use Modules\Boarding\Models\Exeat;
use Modules\Boarding\Models\ExeatQuota;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\Exceptions\InvalidStateTransitionException;

/**
 * ACT-RejectExeat (Book F BRD-03 §4). A rejected exeat never counted
 * as `used` quota — it only releases the `pending` hold it took at
 * request time.
 */
final class RejectExeatAction extends Action
{
    public function execute(RejectExeatData $data): Exeat
    {
        $exeat = Exeat::findOrFail($data->exeatId);

        if ($exeat->status !== 'pending') {
            throw new InvalidStateTransitionException(
                "Exeat #{$exeat->id} must be pending to be rejected (currently {$exeat->status}).",
                ['exeat_id' => $exeat->id, 'status' => $exeat->status],
            );
        }

        return $this->transaction(function () use ($exeat, $data): Exeat {
            $exeat->update(['status' => 'rejected', 'rejection_reason' => $data->rejectionReason]);

            $quota = ExeatQuota::query()
                ->where('student_id', $exeat->student_id)
                ->where('term_id', $exeat->term_id)
                ->where('exeat_type_id', $exeat->exeat_type_id)
                ->first();

            $quota?->decrement('pending');

            event(new ExeatRejected($exeat));

            return $exeat;
        });
    }
}
