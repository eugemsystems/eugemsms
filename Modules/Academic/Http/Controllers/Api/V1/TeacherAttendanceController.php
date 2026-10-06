<?php

declare(strict_types=1);

namespace Modules\Academic\Http\Controllers\Api\V1;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Modules\Academic\Domain\Actions\GenerateAttendanceSessionAction;
use Modules\Academic\Domain\Actions\MarkAttendanceAction;
use Modules\Academic\Domain\DataObjects\GenerateAttendanceSessionData;
use Modules\Academic\Domain\DataObjects\MarkAttendanceData;
use Modules\Academic\Domain\DataObjects\MarkAttendanceRecordInput;
use Modules\Academic\Models\AttendanceReasonCode;
use Modules\Academic\Models\AttendanceRecord;
use Modules\Academic\Models\AttendanceSession;
use Modules\Academic\Models\ClassAllocation;
use Modules\Academic\Models\TimetableSlot;
use Modules\Core\Domain\Support\Auth\PermissionScope;
use Modules\Core\Domain\Support\Auth\PermissionScopeResolver;
use Modules\Core\Domain\Support\SchoolContext;
use Modules\Core\Domain\Support\SessionContext;
use Modules\Core\Http\Support\ApiResponse;
use Modules\Core\Models\SchoolClass;
use Modules\People\Models\Staff;
use Modules\People\Models\Student;

/**
 * `/api/v1/teacher/*` registers (Volume 1 §9.3, ACA-04). The teacher app lists the classes the
 * signed-in teacher may mark, reads a class register for a day, and submits marks — built for
 * offline: every mark carries its own `idempotency_key`, so a queue replayed after load shedding
 * is harmless, and a mark that conflicts with one already stored is reported back, never overwritten.
 *
 * Reach follows `academic.attendance.mark`: school or section reach sees every active class; anything
 * narrower sees the classes the teacher is class teacher of or teaches on the timetable.
 */
final class TeacherAttendanceController
{
    private const array STATUSES = ['present', 'absent', 'late', 'excused'];

    public function classes(Request $request, PermissionScopeResolver $resolver): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return ApiResponse::ok($this->markableClasses($user, $resolver)->map(fn (SchoolClass $class): array => [
            'id' => $class->ulid, 'name' => $class->name,
        ])->values()->all());
    }

    public function register(Request $request, string $class, PermissionScopeResolver $resolver): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $request->validate(['date' => ['nullable', 'date']]);
        $date = Carbon::parse($request->query('date', now()->toDateString()));
        $found = $this->classFor($user, $class, $resolver);

        $roster = $this->roster($found->id, $date);
        $session = AttendanceSession::query()->whereDate('session_date', $date)->where('mode', 'daily')->where('class_id', $found->id)->first();
        $records = $session === null ? collect() : AttendanceRecord::query()->where('session_id', $session->id)->get()->keyBy('student_id');

        return ApiResponse::ok([
            'class' => ['id' => $found->ulid, 'name' => $found->name],
            'date' => $date->toDateString(),
            'status' => $session !== null ? $session->status : 'pending',
            'learners' => $roster->map(fn (Student $student): array => [
                'id' => $student->ulid,
                'admission_number' => $student->admission_number,
                'first_name' => $student->first_name,
                'last_name' => $student->last_name,
                'status' => $records->get($student->id)?->status,
            ])->values()->all(),
            'reason_codes' => AttendanceReasonCode::query()->where('is_active', true)->orderBy('name')->get(['id', 'name'])->map(fn ($code): array => ['id' => $code->id, 'name' => $code->name])->all(),
        ]);
    }

    public function mark(Request $request, string $class, PermissionScopeResolver $resolver, GenerateAttendanceSessionAction $generate, MarkAttendanceAction $mark): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $data = $request->validate([
            'date' => ['required', 'date', 'before_or_equal:today'],
            'records' => ['required', 'array', 'min:1', 'max:200'],
            'records.*.student' => ['required', 'string', 'max:40'],
            'records.*.status' => ['required', 'in:'.implode(',', self::STATUSES)],
            'records.*.reason_code_id' => ['nullable', 'integer'],
            'records.*.minutes_late' => ['nullable', 'integer', 'min:0', 'max:600'],
            'records.*.note' => ['nullable', 'string', 'max:500'],
            'records.*.idempotency_key' => ['required', 'string', 'max:64'],
        ]);

        $found = $this->classFor($user, $class, $resolver);
        $date = Carbon::parse($data['date']);
        abort_unless(SessionContext::isSet() && SessionContext::term() !== null && $date->betweenIncluded(SessionContext::term()->starts_on, SessionContext::term()->ends_on), 422, 'That date is outside the current term.');

        $roster = $this->roster($found->id, $date)->keyBy('ulid');
        abort_if(array_diff(array_column($data['records'], 'student'), $roster->keys()->all()) !== [], 422, 'Some learners are not on this class register.');

        $session = AttendanceSession::query()->whereDate('session_date', $date)->where('mode', 'daily')->where('class_id', $found->id)->first()
            ?? $generate->execute(new GenerateAttendanceSessionData(
                schoolId: (int) SchoolContext::current()?->id,
                academicYearId: SessionContext::year()->id,
                termId: (int) SessionContext::termId(),
                sessionDate: $date,
                mode: 'daily',
                classId: $found->id,
                deviceSource: 'mobile',
            ));

        $result = $mark->execute(new MarkAttendanceData($session->id, array_map(fn (array $r): MarkAttendanceRecordInput => new MarkAttendanceRecordInput(
            studentId: $roster[$r['student']]->id,
            status: $r['status'],
            reasonCodeId: $r['reason_code_id'] ?? null,
            minutesLate: $r['minutes_late'] ?? null,
            note: $r['note'] ?? null,
            idempotencyKey: $r['idempotency_key'],
        ), $data['records']), $user->id));

        return ApiResponse::ok([
            'session_status' => $session->fresh()?->status,
            'conflicts' => collect($result->conflicts)->map(fn (array $c): array => [
                'student' => $roster->first(fn (Student $s): bool => $s->id === $c['student_id'])?->ulid,
                'existing_status' => $c['existing_status'],
                'attempted_status' => $c['attempted_status'],
            ])->values()->all(),
        ]);
    }

    private function classFor(User $user, string $ulid, PermissionScopeResolver $resolver): SchoolClass
    {
        $class = $this->markableClasses($user, $resolver)->first(fn (SchoolClass $c): bool => $c->ulid === $ulid);
        abort_if($class === null, 404);

        return $class;
    }

    /**
     * @return Collection<int, SchoolClass>
     */
    private function markableClasses(User $user, PermissionScopeResolver $resolver): Collection
    {
        $scope = $resolver->resolve($user, 'academic.attendance.mark', SchoolContext::current()?->id);
        abort_if($scope === null, 403);

        $query = SchoolClass::query()->where('is_active', true)->orderBy('name');

        if ($scope->isAtLeastAsWideAs(PermissionScope::Section)) {
            return $query->get();
        }

        $staffId = Staff::query()->where('user_id', $user->id)->value('id');
        $taught = $staffId === null ? [] : TimetableSlot::query()->where('staff_id', $staffId)->whereNotNull('class_id')->pluck('class_id')->all();

        return $query->where(fn ($q) => $q->where('class_teacher_id', $user->id)->orWhereIn('id', $taught))->get();
    }

    /**
     * @return Collection<int, Student>
     */
    private function roster(int $classId, Carbon $date): Collection
    {
        return ClassAllocation::query()
            ->where('class_id', $classId)
            ->where('status', 'confirmed')
            ->whereDate('effective_from', '<=', $date->toDateString())
            ->where(fn ($q) => $q->whereNull('effective_to')->orWhereDate('effective_to', '>', $date->toDateString()))
            ->with('student')
            ->get()
            ->pluck('student')
            ->filter()
            ->sortBy('last_name')
            ->values();
    }
}
