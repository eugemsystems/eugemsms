<?php

declare(strict_types=1);

namespace Modules\Comms\Domain\Actions;

use Illuminate\Support\Carbon;
use Modules\Comms\Models\Complaint;
use Modules\Core\Domain\Actions\Action;

/**
 * ACT-ResolveComplaint (Book I COM-08 §3/BR-COM-08-008).
 */
final class ResolveComplaintAction extends Action
{
    public function execute(int $complaintId, string $resolution, ?int $satisfactionRating = null): Complaint
    {
        return $this->transaction(function () use ($complaintId, $resolution, $satisfactionRating): Complaint {
            $complaint = Complaint::findOrFail($complaintId);
            $complaint->update([
                'status' => 'resolved',
                'resolution' => $resolution,
                'satisfaction_rating' => $satisfactionRating,
                'closed_at' => Carbon::now(),
            ]);

            return $complaint;
        });
    }
}
