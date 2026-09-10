<?php

declare(strict_types=1);

namespace Modules\People\Domain\Actions;

use Illuminate\Support\Carbon;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\Support\Settings\ScopeChain;
use Modules\Core\Domain\Support\Settings\SettingResolver;
use Modules\People\Domain\DataObjects\RequestLeaveData;
use Modules\People\Domain\Events\LeaveRequested;
use Modules\People\Domain\Exceptions\LeaveBalanceExceededException;
use Modules\People\Domain\Exceptions\LeaveDocumentRequiredException;
use Modules\People\Domain\Support\LeaveDayMath;
use Modules\People\Models\LeaveBalance;
use Modules\People\Models\LeaveRequest;
use Modules\People\Models\LeaveType;

/**
 * ACT-RequestLeave (Book C PPL-04 §4/BR-PPL-04-010/011/012,
 * AC-PPL-04-003/004). Days move from `available` to `pending` here —
 * `ApproveLeaveRequestAction`/`RejectLeaveRequestAction`/
 * `CancelLeaveRequestAction` are the only other places that touch
 * these buckets, so a balance is never double-counted.
 *
 * BR-PPL-04-012's grace period is simplified: rather than tracking a
 * separately-uploaded document arriving after submission, a request
 * with no document is allowed through only when `startsOn` is within
 * `staff.sick_note_grace_days` of today (i.e. the leave has already
 * started, or starts imminently) — a request made well in advance
 * still needs the document up front.
 */
final class RequestLeaveAction extends Action
{
    public function __construct(
        private readonly SettingResolver $settings,
    ) {}

    public function execute(RequestLeaveData $data): LeaveRequest
    {
        $leaveType = LeaveType::findOrFail($data->leaveTypeId);

        if ($leaveType->requires_document && $data->supportingDocumentId === null) {
            $graceDays = (int) $this->settings->get('staff.sick_note_grace_days', new ScopeChain(schoolId: $data->schoolId));
            $daysUntilStart = Carbon::now()->startOfDay()->diffInDays($data->startsOn->copy()->startOfDay(), absolute: false);

            if ($daysUntilStart > $graceDays) {
                throw LeaveDocumentRequiredException::forLeaveType($leaveType->id);
            }
        }

        $balance = LeaveBalance::firstOrCreate(
            ['staff_id' => $data->staffId, 'leave_type_id' => $data->leaveTypeId, 'academic_year_id' => $data->academicYearId],
            [
                'school_id' => $data->schoolId,
                'entitlement_days' => $leaveType->annual_entitlement_days ?? '0',
                'accrued_days' => '0',
                'carried_forward_days' => '0',
                'taken_days' => '0',
                'pending_days' => '0',
                'available_days' => $leaveType->annual_entitlement_days ?? '0',
            ],
        );

        if (LeaveDayMath::exceeds($data->workingDays, (string) $balance->available_days) && ! $data->approveOverdraft) {
            throw LeaveBalanceExceededException::forRequest($data->staffId, $data->workingDays, (string) $balance->available_days);
        }

        return $this->transaction(function () use ($balance, $data): LeaveRequest {
            $balance->update([
                'available_days' => LeaveDayMath::subtract((string) $balance->available_days, $data->workingDays),
                'pending_days' => LeaveDayMath::add((string) $balance->pending_days, $data->workingDays),
            ]);

            $request = LeaveRequest::create([
                'school_id' => $data->schoolId,
                'staff_id' => $data->staffId,
                'leave_type_id' => $data->leaveTypeId,
                'academic_year_id' => $data->academicYearId,
                'starts_on' => $data->startsOn->toDateString(),
                'ends_on' => $data->endsOn->toDateString(),
                'working_days' => $data->workingDays,
                'reason' => $data->reason,
                'supporting_document_id' => $data->supportingDocumentId,
                'cover_staff_id' => $data->coverStaffId,
                'status' => 'pending',
                'contact_while_away' => $data->contactWhileAway,
                'created_at' => Carbon::now(),
            ]);

            event(new LeaveRequested($request));

            return $request;
        });
    }
}
