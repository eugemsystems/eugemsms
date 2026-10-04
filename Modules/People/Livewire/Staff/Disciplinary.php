<?php

declare(strict_types=1);

namespace Modules\People\Livewire\Staff;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request as RequestFacade;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Core\Domain\Exceptions\DomainException;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\School;
use Modules\People\Domain\Actions\AdvanceDisciplinaryCaseStageAction;
use Modules\People\Domain\Actions\ReportDisciplinaryCaseAction;
use Modules\People\Domain\Actions\ViewDisciplinaryCaseAction;
use Modules\People\Domain\DataObjects\AdvanceDisciplinaryCaseStageData;
use Modules\People\Domain\DataObjects\ReportDisciplinaryCaseData;
use Modules\People\Domain\DataObjects\ViewDisciplinaryCaseData;
use Modules\People\Models\Staff;
use Modules\People\Models\StaffDisciplinaryCase;

/**
 * `People\Staff\Disciplinary` (Book C PPL-04 §5/BR-PPL-04-020 ⭐,
 * `people.staff.disciplinary_manage`). The list only ever shows
 * non-sensitive columns (case number, category, stage, incident date)
 * from a plain query — `description`/`outcome` are never queried
 * directly here. Opening a case's full detail goes through
 * `ViewDisciplinaryCaseAction`, the only sanctioned read path, which
 * writes the access to `data_access_log`; the result is converted to
 * a plain array immediately (Livewire has no synth for an arbitrary
 * Eloquent/DTO object held as a public property — see
 * `.ai/rules/people.md`). View and manage share one permission here
 * rather than the spec's separate `.view`/`.manage` pair, since this
 * pass has no separate reporting-only workflow that would need the
 * narrower one.
 */
#[Title('Disciplinary cases')]
#[Layout('layouts.app')]
final class Disciplinary extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;
    use Toasts;

    public Staff $staff;

    public string $category = '';

    public string $description = '';

    public string $incidentDate = '';

    public bool $isConfidential = true;

    public ?int $viewingCaseId = null;

    /**
     * @var array{caseNumber: string, category: string, description: string, stage: string, outcome: ?string, isConfidential: bool}|null
     */
    public ?array $viewedCase = null;

    public string $targetStage = '';

    public string $outcome = '';

    public string $outcomeDate = '';

    public function mount(School $school, Staff $staff): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('people.staff.disciplinary_manage');

        abort_unless($staff->school_id === $school->id, 404);

        $this->staff = $staff;
        $this->incidentDate = now()->toDateString();
    }

    public function report(): void
    {
        $this->validate([
            'category' => ['required', 'string', 'max:60'],
            'description' => ['required', 'string'],
            'incidentDate' => ['required', 'date'],
        ]);

        app(ReportDisciplinaryCaseAction::class)->execute(new ReportDisciplinaryCaseData(
            schoolId: $this->school->id,
            staffId: $this->staff->id,
            category: $this->category,
            description: $this->description,
            incidentDate: Carbon::parse($this->incidentDate),
            reportedByUserId: (int) Auth::id(),
            isConfidential: $this->isConfidential,
        ));

        $this->reset(['category', 'description']);
        $this->toast(__('Case reported.'));
    }

    public function view(int $caseId): void
    {
        $case = app(ViewDisciplinaryCaseAction::class)->execute(new ViewDisciplinaryCaseData(
            caseId: $caseId,
            viewedByUserId: (int) Auth::id(),
            ip: RequestFacade::ip(),
        ));

        $this->viewingCaseId = $case->id;
        $this->viewedCase = [
            'caseNumber' => $case->case_number,
            'category' => $case->category,
            'description' => $case->description,
            'stage' => $case->stage,
            'outcome' => $case->outcome,
            'isConfidential' => $case->is_confidential,
        ];
    }

    public function advanceStage(): void
    {
        $this->validate(['targetStage' => ['required', 'string']]);

        try {
            app(AdvanceDisciplinaryCaseStageAction::class)->execute(new AdvanceDisciplinaryCaseStageData(
                caseId: (int) $this->viewingCaseId,
                targetStage: $this->targetStage,
                outcome: $this->outcome !== '' ? $this->outcome : null,
                outcomeDate: $this->outcomeDate !== '' ? Carbon::parse($this->outcomeDate) : null,
            ));
        } catch (DomainException $e) {
            $this->toast($e->getMessage(), 'danger');

            return;
        }

        $this->view((int) $this->viewingCaseId);
        $this->toast(__('Case stage advanced.'));
    }

    public function render(): View
    {
        return view('people::staff.disciplinary', [
            'cases' => StaffDisciplinaryCase::where('staff_id', $this->staff->id)
                ->select(['id', 'case_number', 'category', 'stage', 'incident_date', 'is_confidential'])
                ->orderByDesc('id')
                ->get(),
        ]);
    }
}
