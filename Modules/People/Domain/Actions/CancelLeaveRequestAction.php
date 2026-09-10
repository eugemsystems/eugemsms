<?php

declare(strict_types=1);

namespace Modules\People\Domain\Actions;

use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\Exceptions\InvalidStateTransitionException;
use Modules\People\Domain\DataObjects\CancelLeaveRequestData;
use Modules\People\Domain\Events\LeaveCancelled;
use Modules\People\Domain\Support\LeaveDayMath;
use Modules\People\Models\LeaveBalance;
use Modules\People\Models\LeaveRequest;
use Modules\People\Models\Staff;

/**
 * ACT-CancelLeaveRequest (Book C PPL-04 §4/BR-PPL-04-011). Releases
 * whichever bucket currently holds the days — `pending` for a request
 * not yet decided, `taken` for one already approved — back to
 * `available`, and restores the staff member's status if they were
 * marked `on_leave`.
 */
final class CancelLeaveRequestAction extends Action
{
    private const array CANCELLABLE_FROM = ['pending', 'approved'];

    public function execute(CancelLeaveRequestData $data): LeaveRequest
    {
        $request = LeaveRequest::findOrFail($data->leaveRequestId);

        if (! in_array($request->status, self::CANCELLABLE_FROM, true)) {
            throw new InvalidStateTransitionException(
                "A leave request in [{$request->status}] cannot be cancelled.",
                ['status' => $request->status],
            );
        }

        return $this->transaction(function () use ($request): LeaveRequest {
            $balance = LeaveBalance::where('staff_id', $request->staff_id)
                ->where('leave_type_id', $request->leave_type_id)
                ->where('academic_year_id', $request->academic_year_id)
                ->firstOrFail();

            $wasApproved = $request->status === 'approved';
            $bucket = $wasApproved ? 'taken_days' : 'pending_days';

            $balance->update([
                $bucket => LeaveDayMath::subtract((string) $balance->{$bucket}, (string) $request->working_days),
                'available_days' => LeaveDayMath::add((string) $balance->available_days, (string) $request->working_days),
            ]);

            $request->update(['status' => 'cancelled']);

            if ($wasApproved) {
                Staff::whereKey($request->staff_id)->where('status', 'on_leave')->update(['status' => 'active']);
            }

            event(new LeaveCancelled($request));

            return $request;
        });
    }
}
