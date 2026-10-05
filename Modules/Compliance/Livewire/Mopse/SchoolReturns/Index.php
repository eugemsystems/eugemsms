<?php

declare(strict_types=1);

namespace Modules\Compliance\Livewire\Mopse\SchoolReturns;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Compliance\Domain\Actions\ExportStatutorySchoolReturnAction;
use Modules\Compliance\Domain\Actions\GenerateStatutorySchoolReturnAction;
use Modules\Compliance\Domain\Actions\RecordStatutorySchoolReturnSubmissionAction;
use Modules\Compliance\Domain\Actions\RunDataQualityChecksAction;
use Modules\Compliance\Domain\DataObjects\GenerateStatutorySchoolReturnData;
use Modules\Compliance\Domain\DataObjects\RecordStatutorySchoolReturnSubmissionData;
use Modules\Compliance\Models\DataQualityCheck;
use Modules\Compliance\Models\StatutorySchoolReturn;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\School;

/**
 * `Compliance\Mopse\SchoolReturns` (Book H3 CMP-02, `mopse.manage` to
 * generate/export/submit, `mopse.view` to view only — no spec screens
 * table exists for CMP-02, see this book's own `11-book-h3-...md` §4;
 * these permission names and the screen shape are this pass's own
 * design, following the module's scope line and the shape every other
 * module's own "generate → review → export → submit" lifecycle already
 * uses, e.g. `Reports\Close\Checklist`). Folds the whole return
 * lifecycle onto one action-bar screen rather than separate routes per
 * step, matching that same precedent: generate (which runs data
 * quality validation first and freezes a snapshot, BR-CMP-02-001/002),
 * export, then record submission. `RunDataQualityChecksAction` is also
 * exposed directly so quality issues can be reviewed before generating.
 */
#[Title('MoPSE / EMIS returns')]
#[Layout('layouts.app')]
final class Index extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;
    use Toasts;

    public string $returnType = 'annual_schools_census';

    public string $periodReference = '';

    public string $dueDate = '';

    public string $authority = 'MoPSE District';

    public ?int $selectedReturnId = null;

    public string $acknowledgementRef = '';

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('mopse.view');
    }

    public function runQualityChecks(): void
    {
        $this->authorizePermission('mopse.manage');

        $checks = app(RunDataQualityChecksAction::class)->execute($this->school->id);

        $issues = $checks->sum('affected_count');
        $this->toast(__(':issues data quality issue(s) found across :checks check(s).', ['issues' => $issues, 'checks' => $checks->count()]));
    }

    public function generate(): void
    {
        $this->authorizePermission('mopse.manage');

        $this->validate([
            'returnType' => ['required', 'in:annual_schools_census,term_enrolment,staff_establishment,infrastructure,inspection_pack'],
            'periodReference' => ['required', 'string', 'max:20'],
            'dueDate' => ['required', 'date'],
            'authority' => ['required', 'string', 'max:80'],
        ]);

        $result = app(GenerateStatutorySchoolReturnAction::class)->execute(new GenerateStatutorySchoolReturnData(
            schoolId: $this->school->id,
            returnType: $this->returnType,
            periodReference: $this->periodReference,
            dueDate: Carbon::parse($this->dueDate),
            authority: $this->authority,
            generatedByUserId: (int) auth()->id(),
        ));

        $this->selectedReturnId = $result->return->id;

        $this->toast($result->wasAlreadyGenerated
            ? __('Already generated — showing the frozen snapshot plus any divergence from current data.')
            : __('Return generated — :issues quality issue(s) flagged.', ['issues' => $result->return->quality_issues]));
    }

    public function export(int $returnId): void
    {
        $this->authorizePermission('mopse.manage');

        app(ExportStatutorySchoolReturnAction::class)->execute($returnId, (int) auth()->id());

        $this->toast(__('Return exported.'));
    }

    public function recordSubmission(int $returnId): void
    {
        $this->authorizePermission('mopse.manage');

        app(RecordStatutorySchoolReturnSubmissionAction::class)->execute(new RecordStatutorySchoolReturnSubmissionData(
            returnId: $returnId,
            submittedByUserId: (int) auth()->id(),
            acknowledgementRef: $this->acknowledgementRef !== '' ? $this->acknowledgementRef : null,
        ));

        $this->reset(['acknowledgementRef']);
        $this->toast(__('Submission recorded.'));
    }

    public function render(): View
    {
        return view('compliance::mopse.school-returns.index', [
            'returns' => StatutorySchoolReturn::where('school_id', $this->school->id)->orderByDesc('due_date')->get(),
            'qualityChecks' => DataQualityCheck::where('school_id', $this->school->id)->orderBy('check_key')->get(),
        ]);
    }
}
