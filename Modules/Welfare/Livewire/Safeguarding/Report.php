<?php

declare(strict_types=1);

namespace Modules\Welfare\Livewire\Safeguarding;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\School;
use Modules\People\Models\Student;
use Modules\Welfare\Domain\Actions\FindConcernByAnonymousTokenAction;
use Modules\Welfare\Domain\Actions\ReportAnonymousConcernAction;
use Modules\Welfare\Domain\Actions\ReportSafeguardingConcernAction;
use Modules\Welfare\Domain\DataObjects\ReportAnonymousConcernData;
use Modules\Welfare\Domain\DataObjects\ReportSafeguardingConcernData;

/**
 * `Safeguarding\Report` (Book G BRD-08 §6/§9, `safeguarding.report` —
 * every staff member holds this). Deliberately low-friction: minimal
 * fields, no approval to submit (BR-BRD-08-009). Choosing "report
 * anonymously" routes through `ReportAnonymousConcernAction`, which
 * never takes a reporter id at all — not encrypted, not hashed,
 * structurally absent (AC-BRD-08-007). This screen shows no list of
 * other concerns and queries no `SafeguardingConcern`/`SafeguardingCase`
 * content directly — the only read path here is the single, exact
 * anonymous-token lookup a reporter already holds, which returns no
 * more than its own triage status.
 */
#[Title('Report a safeguarding concern')]
#[Layout('layouts.app')]
final class Report extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;
    use Toasts;

    public ?int $studentId = null;

    public string $concernCategory = 'other';

    public string $description = '';

    public bool $immediateRisk = false;

    public bool $reportAnonymously = false;

    public ?string $initialActionTaken = null;

    public ?string $generatedToken = null;

    public string $followUpToken = '';

    public ?string $followUpStatus = null;

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('safeguarding.report');
    }

    public function submit(): void
    {
        $this->validate([
            'concernCategory' => ['required', 'string'],
            'description' => ['required', 'string'],
        ]);

        if ($this->reportAnonymously) {
            $concern = app(ReportAnonymousConcernAction::class)->execute(new ReportAnonymousConcernData(
                schoolId: $this->school->id,
                concernCategory: $this->concernCategory,
                description: $this->description,
                reportedAt: Carbon::now(),
                studentId: $this->studentId,
                immediateRisk: $this->immediateRisk,
            ));

            $this->generatedToken = $concern->anonymous_token;
            $this->toast(__('Reported anonymously. Save your token — it is the only way to follow up and is shown once.'));
        } else {
            app(ReportSafeguardingConcernAction::class)->execute(new ReportSafeguardingConcernData(
                schoolId: $this->school->id,
                reportSource: 'staff',
                concernCategory: $this->concernCategory,
                description: $this->description,
                reportedAt: Carbon::now(),
                studentId: $this->studentId,
                reporterUserId: (int) Auth::id(),
                immediateRisk: $this->immediateRisk,
                initialActionTaken: $this->initialActionTaken,
            ));

            $this->toast(__('Concern reported to the safeguarding lead.'));
        }

        $this->reset(['description', 'initialActionTaken', 'immediateRisk']);
    }

    public function followUp(): void
    {
        $concern = app(FindConcernByAnonymousTokenAction::class)->execute(trim($this->followUpToken));

        $this->followUpStatus = $concern === null
            ? __('No report found for that token.')
            : __('Status: :status', ['status' => str_replace('_', ' ', $concern->triage_status)]);
    }

    public function render(): View
    {
        return view('welfare::safeguarding.report', [
            'students' => Student::where('school_id', $this->school->id)->orderBy('first_name')->limit(300)->get(['id', 'first_name', 'last_name']),
        ]);
    }
}
