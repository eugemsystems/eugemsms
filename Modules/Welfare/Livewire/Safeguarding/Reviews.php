<?php

declare(strict_types=1);

namespace Modules\Welfare\Livewire\Safeguarding;

use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\School;
use Modules\People\Models\Student;
use Modules\Welfare\Domain\Actions\ListOverdueRiskAssessmentsAction;
use Modules\Welfare\Domain\Actions\ListOverdueVulnerableReviewsAction;
use Modules\Welfare\Livewire\Safeguarding\Concerns\BlocksVendorAndImpersonation;

/**
 * `Safeguarding\Reviews` (Book G BRD-08 §6, access: lead —
 * `safeguarding.lead`). Deliberately conservative: a risk assessment's
 * own `risk_level`/`rationale`/`mitigation_plan` are case content per
 * the task's own instruction and are never shown here — only the
 * `case_id` reference and the review due date, which the lead (whose
 * access to every case is already unconditional) can follow through to
 * `Safeguarding\CaseDetail` for the full, audited read. This is a
 * narrower reading than strictly necessary for the lead alone, chosen
 * because BR-BRD-08-005 says "every read... reads, not just writes"
 * with no carve-out for the lead, and this dashboard has nowhere to log
 * a per-row read the way `ViewSafeguardingCaseAction` does.
 */
#[Title('Overdue safeguarding reviews')]
#[Layout('layouts.app')]
final class Reviews extends Component
{
    use AuthorizesPermissions;
    use BlocksVendorAndImpersonation;
    use InteractsWithSchool;

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->abortIfVendorOrImpersonating();
        $this->authorizePermission('safeguarding.lead');
    }

    public function render(): View
    {
        $overdueVulnerable = app(ListOverdueVulnerableReviewsAction::class)->execute($this->school->id);

        $students = Student::whereIn('id', $overdueVulnerable->pluck('student_id'))
            ->get(['id', 'first_name', 'last_name'])
            ->keyBy('id');

        return view('welfare::safeguarding.reviews', [
            'overdueRiskAssessments' => app(ListOverdueRiskAssessmentsAction::class)->execute($this->school->id)
                ->map(fn ($a) => ['case_id' => $a->case_id, 'review_due_on' => $a->review_due_on]),
            'overdueVulnerable' => $overdueVulnerable,
            'vulnerableStudents' => $students,
        ]);
    }
}
