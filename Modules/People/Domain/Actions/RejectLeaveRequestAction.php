<?php

declare(strict_types=1);

namespace Modules\People\Domain\Actions;

use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\Exceptions\InvalidStateTransitionException;
use Modules\People\Domain\DataObjects\RejectLeaveRequestData;
use Modules\People\Domain\Support\LeaveDayMath;
use Modules\People\Models\LeaveBalance;
use Modules\People\Models\LeaveRequest;

/**
 * ACT-RejectLeaveRequest (Book C PPL-04 §4/BR-PPL-04-011,
 * AC-PPL-04-004). Rejected days return to `available` — no event is
 * published for a rejection (the spec's §9 events list has none).
 */
final class RejectLeaveRequestAction extends Action
{
    public function execute(RejectLeaveRequestData $data): LeaveRequest
    {
        $request = LeaveRequest::findOrFail($data->leaveRequestId);

        if ($request->status !== 'pending') {
            throw new InvalidStateTransitionException(
                "Only a pending leave request can be rejected; this one is [{$request->status}].",
                ['status' => $request->status],
            );
        }

        return $this->transaction(function () use ($request): LeaveRequest {
            $balance = LeaveBalance::where('staff_id', $request->staff_id)
                ->where('leave_type_id', $request->leave_type_id)
                ->where('academic_year_id', $request->academic_year_id)
                ->firstOrFail();

            $balance->update([
                'pending_days' => LeaveDayMath::subtract((string) $balance->pending_days, (string) $request->working_days),
                'available_days' => LeaveDayMath::add((string) $balance->available_days, (string) $request->working_days),
            ]);

            $request->update(['status' => 'rejected']);

            return $request;
        });
    }
}
