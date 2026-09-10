<?php

declare(strict_types=1);

namespace Modules\Boarding\Domain\Actions;

use Illuminate\Support\Carbon;
use Modules\Boarding\Domain\DataObjects\OpenRollCallData;
use Modules\Boarding\Models\BedAllocation;
use Modules\Boarding\Models\Exeat;
use Modules\Boarding\Models\RollCall;
use Modules\Boarding\Models\RollCallPoint;
use Modules\Boarding\Models\RollCallRecord;
use Modules\Core\Domain\Actions\Action;
use Modules\People\Models\Student;
use Modules\Welfare\Models\Detention;
use Modules\Welfare\Models\ExternalReferral;
use Modules\Welfare\Models\Sanction;
use Modules\Welfare\Models\SickBayAdmission;

/**
 * ACT-OpenRollCall (Book F BRD-02 §2/§4/BR-BRD-02-002/003/007 ⭐,
 * BRD-03 §5/BR-BRD-03-009, Book G BRD-06 §4/BR-BRD-06-015/017, BRD-07
 * §4/BR-BRD-07-010/011). `expected_count` is derived from active
 * `bed_allocations` on `roll_date` every time this runs — never a
 * stored list. Idempotent: re-opening an already-open roll for the
 * same point/hostel/date returns the existing row rather than
 * duplicating it.
 *
 * Pre-population (§4) now wires five of the six sources for real, in
 * priority order (a learner cannot be in two of these states at once,
 * so each subsequent check excludes students already matched): sick
 * bay (`BRD-06`), external referral/hospital (`BRD-06`), suspension
 * (`BRD-07` — `sanctions` with `removes_from_campus` and `status`
 * `active` covering this date), detention (`BRD-07` — `detentions`
 * scheduled for this date whose start/end window covers this roll's
 * scheduled time), exeat (`BRD-03`), then withdrawn (`PPL-01`). The
 * remaining source (a sports fixture) is `OPS-07` — not built in this
 * codebase — so those learners are left unmarked for the housemaster.
 */
final class OpenRollCallAction extends Action
{
    public function execute(OpenRollCallData $data): RollCall
    {
        $point = RollCallPoint::findOrFail($data->rollCallPointId);

        $existing = RollCall::query()
            ->where('roll_call_point_id', $point->id)
            ->where('hostel_id', $data->hostelId)
            ->whereDate('roll_date', $data->rollDate->toDateString())
            ->first();

        if ($existing !== null) {
            return $existing;
        }

        $allocatedStudentIds = BedAllocation::query()
            ->where('hostel_id', $data->hostelId)
            ->where('status', 'confirmed')
            ->whereDate('effective_from', '<=', $data->rollDate->toDateString())
            ->where(fn ($q) => $q->whereNull('effective_to')->orWhereDate('effective_to', '>=', $data->rollDate->toDateString()))
            ->pluck('student_id');

        $scheduledAt = $data->rollDate->copy()->setTimeFromTimeString($point->scheduled_time);

        $exeatsOnDate = Exeat::query()
            ->whereIn('student_id', $allocatedStudentIds)
            ->whereIn('status', ['approved', 'departed'])
            ->where('departs_at', '<=', $scheduledAt)
            ->where('returns_by', '>=', $scheduledAt)
            ->get(['id', 'student_id']);

        return $this->transaction(function () use ($point, $data, $allocatedStudentIds, $scheduledAt, $exeatsOnDate): RollCall {
            $rollCall = RollCall::create([
                'school_id' => $point->school_id,
                'term_id' => $data->termId,
                'roll_call_point_id' => $point->id,
                'hostel_id' => $data->hostelId,
                'roll_date' => $data->rollDate->toDateString(),
                'scheduled_at' => $scheduledAt,
                'expected_count' => $allocatedStudentIds->count(),
                'status' => 'pending',
            ]);

            $sickBayStudentIds = SickBayAdmission::query()
                ->whereIn('student_id', $allocatedStudentIds)
                ->whereIn('status', SickBayAdmission::CURRENTLY_ADMITTED_STATUSES)
                ->pluck('student_id')
                ->unique();

            $hospitalStudentIds = ExternalReferral::query()
                ->whereIn('student_id', $allocatedStudentIds->diff($sickBayStudentIds))
                ->whereIn('status', ExternalReferral::CURRENTLY_AWAY_STATUSES)
                ->pluck('student_id')
                ->unique();

            foreach ($sickBayStudentIds as $studentId) {
                RollCallRecord::create([
                    'school_id' => $point->school_id,
                    'roll_call_id' => $rollCall->id,
                    'student_id' => $studentId,
                    'roll_date' => $data->rollDate->toDateString(),
                    'status' => 'sick_bay',
                    'is_auto_populated' => true,
                    'source_reference' => 'BRD-06 sick_bay_admissions',
                    'marked_at' => Carbon::now(),
                ]);
            }

            foreach ($hospitalStudentIds as $studentId) {
                RollCallRecord::create([
                    'school_id' => $point->school_id,
                    'roll_call_id' => $rollCall->id,
                    'student_id' => $studentId,
                    'roll_date' => $data->rollDate->toDateString(),
                    'status' => 'hospital',
                    'is_auto_populated' => true,
                    'source_reference' => 'BRD-06 external_referrals',
                    'marked_at' => Carbon::now(),
                ]);
            }

            $excludedSoFar = $sickBayStudentIds->merge($hospitalStudentIds);

            $suspendedStudentIds = Sanction::query()
                ->whereIn('student_id', $allocatedStudentIds->diff($excludedSoFar))
                ->whereIn('status', Sanction::ACTIVE_STATUSES)
                ->whereHas('sanctionType', fn ($q) => $q->where('removes_from_campus', true))
                ->whereDate('starts_on', '<=', $data->rollDate->toDateString())
                ->where(fn ($q) => $q->whereNull('ends_on')->orWhereDate('ends_on', '>=', $data->rollDate->toDateString()))
                ->pluck('student_id')
                ->unique();

            foreach ($suspendedStudentIds as $studentId) {
                RollCallRecord::create([
                    'school_id' => $point->school_id,
                    'roll_call_id' => $rollCall->id,
                    'student_id' => $studentId,
                    'roll_date' => $data->rollDate->toDateString(),
                    'status' => 'suspended',
                    'is_auto_populated' => true,
                    'source_reference' => 'BRD-07 sanctions',
                    'marked_at' => Carbon::now(),
                ]);
            }

            $excludedSoFar = $excludedSoFar->merge($suspendedStudentIds);
            $scheduledTime = $scheduledAt->format('H:i:s');

            $detainedStudentIds = Detention::query()
                ->whereIn('student_id', $allocatedStudentIds->diff($excludedSoFar))
                ->whereIn('status', Detention::CURRENTLY_SCHEDULED_STATUSES)
                ->whereDate('scheduled_date', $data->rollDate->toDateString())
                ->where('starts_at', '<=', $scheduledTime)
                ->where('ends_at', '>=', $scheduledTime)
                ->pluck('student_id')
                ->unique();

            foreach ($detainedStudentIds as $studentId) {
                RollCallRecord::create([
                    'school_id' => $point->school_id,
                    'roll_call_id' => $rollCall->id,
                    'student_id' => $studentId,
                    'roll_date' => $data->rollDate->toDateString(),
                    'status' => 'detention',
                    'is_auto_populated' => true,
                    'source_reference' => 'BRD-07 detentions',
                    'marked_at' => Carbon::now(),
                ]);
            }

            $onExeatStudentIds = $exeatsOnDate->pluck('student_id')->unique()->diff($sickBayStudentIds)->diff($hospitalStudentIds)->diff($suspendedStudentIds)->diff($detainedStudentIds);
            $exeatByStudent = $exeatsOnDate->keyBy('student_id');

            foreach ($onExeatStudentIds as $studentId) {
                RollCallRecord::create([
                    'school_id' => $point->school_id,
                    'roll_call_id' => $rollCall->id,
                    'student_id' => $studentId,
                    'roll_date' => $data->rollDate->toDateString(),
                    'status' => 'exeat',
                    'is_auto_populated' => true,
                    'source_reference' => (string) $exeatByStudent->get($studentId)?->id,
                    'marked_at' => Carbon::now(),
                ]);
            }

            $withdrawnStudentIds = Student::query()
                ->whereIn('id', $allocatedStudentIds->diff($onExeatStudentIds)->diff($sickBayStudentIds)->diff($hospitalStudentIds)->diff($suspendedStudentIds)->diff($detainedStudentIds))
                ->where('status', '!=', 'active')
                ->pluck('id');

            foreach ($withdrawnStudentIds as $studentId) {
                RollCallRecord::create([
                    'school_id' => $point->school_id,
                    'roll_call_id' => $rollCall->id,
                    'student_id' => $studentId,
                    'roll_date' => $data->rollDate->toDateString(),
                    'status' => 'withdrawn',
                    'is_auto_populated' => true,
                    'source_reference' => 'PPL-01 status',
                    'marked_at' => Carbon::now(),
                ]);
            }

            return $rollCall;
        });
    }
}
