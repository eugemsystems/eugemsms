<?php

declare(strict_types=1);

namespace Modules\Academic\Livewire\Enrolment;

use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Academic\Models\LearnerSubjectEnrolment;
use Modules\Core\Domain\Support\SessionContext;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Livewire\Sessions\Concerns\InteractsWithSession;
use Modules\Core\Models\School;
use Modules\People\Models\Student;

/**
 * `Enrolment\BillingCheck` (Book D ACA-02 §6 ⭐, `academic.enrolment.view`).
 * The safety net for the whole part-time billing model: for every
 * `PART_TIME` learner this term, the actual billable subject count
 * (active + `is_billable` rows on `learner_subject_enrolments`) versus
 * how many of those rows have actually reached `billing_status =
 * billed`. A mismatch means a billing event is still `pending` or
 * `failed` — surfaced here before a parent notices a wrong invoice.
 */
#[Title('Billing reconciliation')]
#[Layout('layouts.app')]
final class BillingCheck extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;
    use InteractsWithSession;

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->loadSessionContext($school);
        $this->authorizePermission('academic.enrolment.view');
    }

    public function render(): View
    {
        $termId = SessionContext::termId();
        $rows = collect();

        if ($termId !== null) {
            $students = Student::where('school_id', $this->school->id)
                ->where('enrolment_type', 'PART_TIME')
                ->whereNotIn('status', ['withdrawn', 'graduated', 'archived'])
                ->get();

            $enrolmentsByStudent = LearnerSubjectEnrolment::where('term_id', $termId)
                ->where('status', 'active')
                ->where('is_billable', true)
                ->get()
                ->groupBy('student_id');

            foreach ($students as $student) {
                $enrolments = $enrolmentsByStudent->get($student->id, collect());
                $actualCount = $enrolments->count();
                $billedCount = $enrolments->where('billing_status', 'billed')->count();

                $rows->push([
                    'student' => $student,
                    'actualCount' => $actualCount,
                    'billedCount' => $billedCount,
                    'mismatch' => $actualCount !== $billedCount,
                ]);
            }
        }

        return view('academic::enrolment.billing-check', [
            'rows' => $rows,
            'mismatchCount' => $rows->where('mismatch', true)->count(),
        ]);
    }
}
