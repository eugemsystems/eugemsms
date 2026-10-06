<?php

declare(strict_types=1);

namespace Modules\Comms\Domain\Actions;

use InvalidArgumentException;
use Modules\Comms\Models\Complaint;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\Exceptions\InvalidStateTransitionException;

/**
 * ACT-RateComplaintResolution (Book I COM-08 §3/BR-COM-08-008). The raiser rates
 * how the complaint was handled, 1–5, once, after it has been resolved. Only the
 * raiser can rate — an anonymous complaint has no raiser to identify, so it
 * cannot be rated — and a rating is never changed afterwards.
 */
final class RateComplaintResolutionAction extends Action
{
    public function execute(int $complaintId, string $raisedByType, int $raisedById, int $rating): Complaint
    {
        if ($rating < 1 || $rating > 5) {
            throw new InvalidArgumentException('A satisfaction rating is between 1 and 5.');
        }

        return $this->transaction(function () use ($complaintId, $raisedByType, $raisedById, $rating): Complaint {
            $complaint = Complaint::query()->lockForUpdate()->findOrFail($complaintId);

            if ($complaint->raised_by_type !== $raisedByType || (int) $complaint->raised_by_id !== $raisedById) {
                throw new InvalidStateTransitionException('Only the person who raised a complaint can rate its resolution.');
            }

            if ($complaint->status !== 'resolved' && $complaint->status !== 'closed') {
                throw new InvalidStateTransitionException('A complaint can be rated only once it has been resolved.');
            }

            if ($complaint->satisfaction_rating !== null) {
                throw new InvalidStateTransitionException('This complaint has already been rated.');
            }

            $complaint->update(['satisfaction_rating' => $rating]);

            return $complaint;
        });
    }
}
