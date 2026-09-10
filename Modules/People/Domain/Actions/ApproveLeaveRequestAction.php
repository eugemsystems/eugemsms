<?php

declare(strict_types=1);

namespace Modules\People\Domain\Actions;

use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\Exceptions\InvalidStateTransitionException;
use Modules\People\Domain\DataObjects\ApproveLeaveRequestData;
use Modules\People\Domain\Events\LeaveApproved;
use Modules\People\Domain\Support\LeaveDayMath;
use Modules\People\Models\LeaveBalance;
use Modules\People\Models\LeaveRequest;
use Modules\People\Models\Staff;

/**
 * ACT-ApproveLeaveRequest (Book C PPL-04 §4/BR-PPL-04-011/014,
 * AC-PPL-04-004/005). Days move straight from `pending` to `taken` on
 * approval — the spec's own acceptance criterion, not a two-step
 * "approved then taken" bucket. `ACA-03` cover notification and
 * `PPL-05` payroll are both deferred listeners on `LeaveApproved`.
 */
final class ApproveLeaveRequestAction extends Action
{
    public function execute(ApproveLeaveRequestData $data): LeaveRequest
    {
        $request = LeaveRequest::findOrFail($data->leaveRequestId);

        if ($request->status !== 'pending') {
            throw new InvalidStateTransitionException(
                "Only a pending leave request can be approved; this one is [{$request->status}].",
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
                'taken_days' => LeaveDayMath::add((string) $balance->taken_days, (string) $request->working_days),
            ]);

            $request->update(['status' => 'approved']);

            Staff::whereKey($request->staff_id)->update(['status' => 'on_leave']);

            event(new LeaveApproved($request));

            return $request;
        });
    }
}
