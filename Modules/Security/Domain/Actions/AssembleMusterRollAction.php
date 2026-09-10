<?php

declare(strict_types=1);

namespace Modules\Security\Domain\Actions;

use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Modules\Academic\Models\AttendanceRecord;
use Modules\Boarding\Models\BedAllocation;
use Modules\Boarding\Models\Exeat;
use Modules\Boarding\Models\VisitorLogEntry;
use Modules\Core\Domain\Actions\Action;
use Modules\People\Models\LeaveRequest;
use Modules\People\Models\Staff;
use Modules\Security\Domain\DataObjects\MusterRosterEntry;
use Modules\Security\Models\ContractorSiteVisit;
use Modules\Welfare\Models\MedicalCondition;
use Modules\Welfare\Models\SickBayAdmission;

/**
 * ACT-AssembleMusterRoll (Book H2 OPS-06 §3 ⭐⭐/BR-OPS-06-008/009/
 * AC-OPS-06-001). Real, live aggregation across five already-built
 * modules — no shadow "who's on site" table kept independently. Sick
 * bay occupants are their own category and are excluded from the
 * boarder/day-scholar buckets they'd otherwise also appear in, so
 * nobody is counted twice; they and any learner with
 * `requires_evacuation_assistance` recorded (a `Modules\Welfare`
 * `medical_conditions` column this module added — see its own
 * migration docblock) sort first, per BR-OPS-06-009.
 *
 * Deliberately out of scope here: offline caching and 15-minute
 * device refresh (BR-OPS-06-008's own second half) are client/API
 * concerns, and no API or mobile layer exists yet for any module in
 * this codebase — the same boundary every other module's own offline
 * requirement has been held to.
 */
final class AssembleMusterRollAction extends Action
{
    /**
     * @return Collection<int, MusterRosterEntry>
     */
    public function execute(int $schoolId): Collection
    {
        $today = Carbon::now()->toDateString();

        $sickBayStudentIds = SickBayAdmission::where('school_id', $schoolId)
            ->whereIn('status', SickBayAdmission::CURRENTLY_ADMITTED_STATUSES)
            ->whereNull('discharged_at')
            ->pluck('student_id');

        $assistanceStudentIds = MedicalCondition::where('school_id', $schoolId)
            ->where('requires_evacuation_assistance', true)
            ->pluck('student_id');

        $outOnExeatStudentIds = Exeat::where('school_id', $schoolId)
            ->whereIn('status', ['departed', 'overdue'])
            ->pluck('student_id');

        $excludedFromBoarders = $outOnExeatStudentIds->merge($sickBayStudentIds);

        $boarderStudentIds = BedAllocation::where('school_id', $schoolId)
            ->where('status', 'confirmed')
            ->whereNull('effective_to')
            ->whereNotIn('student_id', $excludedFromBoarders)
            ->pluck('student_id');

        $dayScholarStudentIds = AttendanceRecord::where('school_id', $schoolId)
            ->whereDate('session_date', $today)
            ->where('status', 'present')
            ->whereNotIn('student_id', $sickBayStudentIds)
            ->whereHas('student', fn ($query) => $query->where('residency', 'DAY'))
            ->pluck('student_id')
            ->unique();

        $onLeaveStaffIds = LeaveRequest::where('school_id', $schoolId)
            ->where('status', 'approved')
            ->whereDate('starts_on', '<=', $today)
            ->whereDate('ends_on', '>=', $today)
            ->pluck('staff_id');

        $activeStaffIds = Staff::where('school_id', $schoolId)
            ->where('status', 'active')
            ->whereNotIn('id', $onLeaveStaffIds)
            ->pluck('id');

        $signedInVisitorIds = VisitorLogEntry::where('school_id', $schoolId)
            ->whereNull('signed_out_at')
            ->pluck('id');

        $signedInContractorVisitIds = ContractorSiteVisit::where('school_id', $schoolId)
            ->whereNull('signed_out_at')
            ->pluck('id');

        $entries = new Collection;

        foreach ($sickBayStudentIds as $studentId) {
            $entries->push(new MusterRosterEntry('sick_bay', 'student', $studentId, needsAssistance: true));
        }

        foreach ($boarderStudentIds as $studentId) {
            $entries->push(new MusterRosterEntry('boarder', 'student', $studentId, needsAssistance: $assistanceStudentIds->contains($studentId)));
        }

        foreach ($dayScholarStudentIds as $studentId) {
            $entries->push(new MusterRosterEntry('day_scholar', 'student', $studentId, needsAssistance: $assistanceStudentIds->contains($studentId)));
        }

        foreach ($activeStaffIds as $staffId) {
            $entries->push(new MusterRosterEntry('staff', 'staff', $staffId));
        }

        foreach ($signedInVisitorIds as $visitLogId) {
            $entries->push(new MusterRosterEntry('visitor', 'visitor_log_entry', $visitLogId));
        }

        foreach ($signedInContractorVisitIds as $visitId) {
            $entries->push(new MusterRosterEntry('contractor', 'contractor_site_visit', $visitId));
        }

        return $entries->sortByDesc(fn (MusterRosterEntry $entry): bool => $entry->needsAssistance)->values();
    }
}
