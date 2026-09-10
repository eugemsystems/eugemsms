<?php

declare(strict_types=1);

namespace Modules\Comms\Domain\Actions;

use Illuminate\Support\Carbon;
use Modules\Comms\Models\SenderId;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\Exceptions\InvalidStateTransitionException;

/**
 * ACT-UpdateSenderIdStatus (Book I COM-01 §3/BR-COM-01-008). Records
 * the network's own approval decision — `FakeSmsGatewayDriver` reads
 * this status at send time to decide whether this sender ID is usable.
 */
final class UpdateSenderIdStatusAction extends Action
{
    private const array VALID_STATUSES = ['approved', 'rejected', 'expired'];

    public function execute(int $senderIdId, string $status, ?string $rejectionReason = null): SenderId
    {
        if (! in_array($status, self::VALID_STATUSES, true)) {
            throw new InvalidStateTransitionException(
                "Sender ID status must be one of approved, rejected, or expired — got '{$status}'.",
                ['status' => $status],
            );
        }

        return $this->transaction(function () use ($senderIdId, $status, $rejectionReason): SenderId {
            $senderId = SenderId::findOrFail($senderIdId);

            $senderId->update([
                'status' => $status,
                'approved_at' => $status === 'approved' ? Carbon::now() : null,
                'rejection_reason' => $status === 'rejected' ? $rejectionReason : null,
            ]);

            return $senderId;
        });
    }
}
