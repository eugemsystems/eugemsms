<?php

declare(strict_types=1);

namespace Modules\People\Http\Controllers\Api\V1;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Modules\Core\Domain\Support\SessionContext;
use Modules\Core\Http\Support\ApiResponse;
use Modules\People\Domain\Actions\CancelLeaveRequestAction;
use Modules\People\Domain\Actions\RequestLeaveAction;
use Modules\People\Domain\Actions\SwapDutyAssignmentAction;
use Modules\People\Domain\DataObjects\CancelLeaveRequestData;
use Modules\People\Domain\DataObjects\RequestLeaveData;
use Modules\People\Domain\DataObjects\SwapDutyAssignmentData;
use Modules\People\Models\DutyAssignment;
use Modules\People\Models\LeaveBalance;
use Modules\People\Models\LeaveRequest;
use Modules\People\Models\Staff;
use Modules\People\Models\StaffWorkload;
use Modules\People\Models\TeacherAllocation;

/**
 * Book C PPL-04 §6 — the staff self-service `/api/v1/me/*` surface. `/me/timetable` is
 * deliberately not here: the spec itself delegates it to ACA-03, which has no `/api/v1` surface of
 * its own yet (not a PPL-04 gap).
 *
 * Every method resolves the acting `Staff` record the same way `TeacherAttendanceController`/
 * `TeacherMarksController` already do — `Staff::where('user_id', $user->id)` — not a route
 * parameter, so nobody can read or act on another staff member's own record through these routes.
 */
final class StaffSelfServiceController
{
    public function profile(Request $request): JsonResponse
    {
        $staff = $this->staff($request);

        return ApiResponse::ok([
            'id' => $staff->ulid,
            'staff_number' => $staff->staff_number,
            'first_name' => $staff->first_name,
            'last_name' => $staff->last_name,
            'staff_category' => $staff->staff_category,
            'department_id' => $staff->department_id,
            'post_id' => $staff->post_id,
            'is_teaching' => $staff->is_teaching,
            'max_weekly_periods' => $staff->max_weekly_periods,
            'status' => $staff->status,
        ]);
    }

    public function allocations(Request $request): JsonResponse
    {
        $staff = $this->staff($request);
        $termId = $request->integer('term') ?: SessionContext::termId();

        $allocations = TeacherAllocation::query()
            ->where('staff_id', $staff->id)
            ->when($termId !== null, fn ($q) => $q->where('term_id', $termId))
            ->where('status', 'active')
            ->with(['subject:id,name', 'schoolClass:id,name'])
            ->get();

        return ApiResponse::ok($allocations->map(fn (TeacherAllocation $allocation): array => [
            'subject' => $allocation->subject?->name,
            'class' => $allocation->schoolClass?->name,
            'role' => $allocation->role,
            'weekly_periods' => $allocation->weekly_periods,
            'is_class_teacher' => $allocation->is_class_teacher,
        ])->values()->all());
    }

    public function workload(Request $request): JsonResponse
    {
        $staff = $this->staff($request);
        $termId = $request->integer('term') ?: SessionContext::termId();

        $workload = StaffWorkload::query()
            ->where('staff_id', $staff->id)
            ->when($termId !== null, fn ($q) => $q->where('term_id', $termId))
            ->first();

        if ($workload === null) {
            return ApiResponse::ok(['term_id' => $termId, 'computed' => false]);
        }

        return ApiResponse::ok([
            'term_id' => $workload->term_id,
            'computed' => true,
            'teaching_periods' => $workload->teaching_periods,
            'class_teacher_count' => $workload->class_teacher_count,
            'duty_count' => $workload->duty_count,
            'subject_count' => $workload->subject_count,
            'class_count' => $workload->class_count,
            'learner_count' => $workload->learner_count,
            'utilisation_percent' => $workload->utilisation_percent,
            'is_overloaded' => $workload->is_overloaded,
        ]);
    }

    public function duties(Request $request): JsonResponse
    {
        $staff = $this->staff($request);
        $data = $request->validate(['from' => ['nullable', 'date'], 'to' => ['nullable', 'date']]);

        $duties = DutyAssignment::query()
            ->where('staff_id', $staff->id)
            ->when(isset($data['from']), fn ($q) => $q->where('starts_at', '>=', Carbon::parse($data['from'])))
            ->when(isset($data['to']), fn ($q) => $q->where('starts_at', '<=', Carbon::parse($data['to'])))
            ->orderBy('starts_at')
            ->get();

        return ApiResponse::ok($duties->map(fn (DutyAssignment $duty): array => [
            'id' => $duty->id,
            'starts_at' => $duty->starts_at->toIso8601ZuluString(),
            'ends_at' => $duty->ends_at->toIso8601ZuluString(),
            'status' => $duty->status,
        ])->values()->all());
    }

    /**
     * `SwapDutyAssignmentAction` performs an already-consented swap immediately — the backend
     * models no separate pending/approval state for a swap (`duty_assignments.status` has no such
     * value). A self-service "request" therefore means the caller is asserting the other party
     * already agreed (`both_parties_consented` is a required field, not assumed), and the caller
     * is recorded as their own approver — the same honest-about-the-gap approach this codebase
     * takes elsewhere rather than inventing an approval workflow the backend doesn't have.
     */
    public function requestDutySwap(Request $request, string $assignment): JsonResponse
    {
        $staff = $this->staff($request);
        $data = $request->validate([
            'new_staff_id' => ['required', 'string'],
            'both_parties_consented' => ['required', 'accepted'],
        ]);

        $duty = DutyAssignment::query()->where('id', $assignment)->where('staff_id', $staff->id)->first();
        abort_if($duty === null, 404);

        $newStaff = Staff::query()->where('ulid', $data['new_staff_id'])->first();
        abort_if($newStaff === null, 404);

        // A thrown InvalidStateTransitionException (DomainException) is rendered by the global
        // handler in bootstrap/app.php with its own specific error code -- not caught here, so
        // that code reaches the client instead of a generic one.
        $swapped = app(SwapDutyAssignmentAction::class)->execute(new SwapDutyAssignmentData(
            assignmentId: $duty->id,
            newStaffId: $newStaff->id,
            approvedByUserId: (int) $request->user()?->id,
            bothPartiesConsented: true,
        ));

        return ApiResponse::ok(['status' => $swapped->status]);
    }

    public function leaveBalances(Request $request): JsonResponse
    {
        $staff = $this->staff($request);

        $balances = LeaveBalance::query()->where('staff_id', $staff->id)->with('leaveType:id,name')->get();

        return ApiResponse::ok($balances->map(fn (LeaveBalance $balance): array => [
            'leave_type' => $balance->leaveType?->name,
            'entitlement_days' => $balance->entitlement_days,
            'accrued_days' => $balance->accrued_days,
            'carried_forward_days' => $balance->carried_forward_days,
            'taken_days' => $balance->taken_days,
            'pending_days' => $balance->pending_days,
            'available_days' => $balance->available_days,
        ])->values()->all());
    }

    public function leaveRequests(Request $request): JsonResponse
    {
        $staff = $this->staff($request);

        $requests = LeaveRequest::query()->where('staff_id', $staff->id)->orderByDesc('starts_on')->get();

        return ApiResponse::ok($requests->map(fn (LeaveRequest $leaveRequest): array => [
            'id' => $leaveRequest->ulid,
            'leave_type_id' => $leaveRequest->leave_type_id,
            'starts_on' => $leaveRequest->starts_on->toDateString(),
            'ends_on' => $leaveRequest->ends_on->toDateString(),
            'working_days' => $leaveRequest->working_days,
            'status' => $leaveRequest->status,
            'reason' => $leaveRequest->reason,
        ])->values()->all());
    }

    public function storeLeaveRequest(Request $request): JsonResponse
    {
        $staff = $this->staff($request);
        $data = $request->validate([
            'leave_type_id' => ['required', 'integer'],
            'starts_on' => ['required', 'date'],
            'ends_on' => ['required', 'date', 'after_or_equal:starts_on'],
            'working_days' => ['required', 'numeric'],
            'reason' => ['nullable', 'string', 'max:500'],
            'cover_staff_id' => ['nullable', 'string'],
            'contact_while_away' => ['nullable', 'string', 'max:150'],
        ]);

        $yearId = SessionContext::yearId();
        abort_if($yearId === null, 422);

        $coverStaffId = isset($data['cover_staff_id']) ? Staff::query()->where('ulid', $data['cover_staff_id'])->value('id') : null;

        // A thrown LeaveDocumentRequiredException/LeaveBalanceExceededException (both
        // DomainException) is rendered by the global handler with its own specific error code --
        // not caught here, so that code reaches the client instead of a generic one.
        $leaveRequest = app(RequestLeaveAction::class)->execute(new RequestLeaveData(
            schoolId: $staff->school_id,
            staffId: $staff->id,
            leaveTypeId: $data['leave_type_id'],
            academicYearId: $yearId,
            startsOn: Carbon::parse($data['starts_on']),
            endsOn: Carbon::parse($data['ends_on']),
            workingDays: (string) $data['working_days'],
            reason: $data['reason'] ?? null,
            coverStaffId: $coverStaffId,
            contactWhileAway: $data['contact_while_away'] ?? null,
        ));

        return ApiResponse::ok(['id' => $leaveRequest->ulid, 'status' => $leaveRequest->status], status: 201);
    }

    public function cancelLeaveRequest(Request $request, string $leaveRequest): JsonResponse
    {
        $staff = $this->staff($request);
        $found = LeaveRequest::query()->where('ulid', $leaveRequest)->where('staff_id', $staff->id)->first();
        abort_if($found === null, 404);

        app(CancelLeaveRequestAction::class)->execute(new CancelLeaveRequestData(
            leaveRequestId: $found->id,
            cancelledByUserId: (int) $request->user()?->id,
        ));

        return ApiResponse::ok(['status' => 'cancelled']);
    }

    private function staff(Request $request): Staff
    {
        /** @var User $user */
        $user = $request->user();

        $staff = Staff::query()->where('user_id', $user->id)->first();
        abort_if($staff === null, 404);

        return $staff;
    }
}
