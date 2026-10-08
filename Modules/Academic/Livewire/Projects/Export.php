<?php

declare(strict_types=1);

namespace Modules\Academic\Livewire\Projects;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Academic\Domain\Actions\GenerateProjectNationalSubmissionExportAction;
use Modules\Academic\Domain\Actions\ValidateProjectNationalSubmissionAction;
use Modules\Academic\Domain\DataObjects\GenerateProjectNationalSubmissionData;
use Modules\Academic\Domain\DataObjects\ValidateProjectNationalSubmissionData;
use Modules\Academic\Domain\Exceptions\ProjectSubmissionExportBlockedException;
use Modules\Academic\Models\AssessmentInstrument;
use Modules\Academic\Models\ProjectNationalSubmission;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\AcademicYear;
use Modules\Core\Models\School;

/**
 * `Projects\Export` (Book E ACA-06 §6/§7/BR-ACA-06-018, `academic.projects.export`
 * ⚠ — the national submission export). Validation report first, always
 * ("with a validation report run before export" — the rule's own
 * wording): `validate()` must be run, and show zero `error`-severity
 * issues, before `export()` is offered anything to call.
 * `GenerateProjectNationalSubmissionExportAction` refuses outright
 * server-side regardless, so a stale/bypassed client state cannot skip
 * the check. The output format is a school's own configurable
 * `DocumentTemplate` — never a hard-coded Ministry layout, since none
 * is specified anywhere to build against.
 */
#[Title('National submission export')]
#[Layout('layouts.app')]
final class Export extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;
    use Toasts;

    public ?int $instrumentId = null;

    public ?int $academicYearId = null;

    /** @var array<int, array{learner_project_id: int, student_name: string, admission_number: string, field: string, severity: string, message: string}>|null */
    public ?array $issues = null;

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('academic.projects.export');
    }

    public function updatedInstrumentId(): void
    {
        $this->issues = null;
    }

    public function updatedAcademicYearId(): void
    {
        $this->issues = null;
    }

    public function validateSubmission(): void
    {
        $this->authorizePermission('academic.projects.export');

        if ($this->instrumentId === null || $this->academicYearId === null) {
            $this->toast(__('Choose an instrument and an academic year first.'), 'danger');

            return;
        }

        $issues = app(ValidateProjectNationalSubmissionAction::class)->execute(new ValidateProjectNationalSubmissionData(
            instrumentId: $this->instrumentId, academicYearId: $this->academicYearId,
        ));

        $this->issues = $issues->map(fn ($issue): array => [
            'learner_project_id' => $issue->learnerProjectId,
            'student_name' => $issue->studentName,
            'admission_number' => $issue->admissionNumber,
            'field' => $issue->field,
            'severity' => $issue->severity,
            'message' => $issue->message,
        ])->all();

        $this->toast(count($this->issues) === 0 ? __('No issues found.') : __('Validation complete — review the report below.'));
    }

    public function export(): void
    {
        $this->authorizePermission('academic.projects.export');

        if ($this->instrumentId === null || $this->academicYearId === null) {
            return;
        }

        try {
            app(GenerateProjectNationalSubmissionExportAction::class)->execute(new GenerateProjectNationalSubmissionData(
                instrumentId: $this->instrumentId, academicYearId: $this->academicYearId, generatedByUserId: (int) Auth::id(),
            ));
        } catch (ProjectSubmissionExportBlockedException $e) {
            $this->toast($e->getMessage(), 'danger');

            return;
        }

        $this->toast(__('National submission exported.'));
    }

    public function render(): View
    {
        $errorCount = collect($this->issues ?? [])->where('severity', 'error')->count();

        return view('academic::projects.export', [
            'instruments' => AssessmentInstrument::where('school_id', $this->school->id)->where('status', 'active')->orderBy('name')->get(),
            'years' => AcademicYear::where('school_id', $this->school->id)->orderByDesc('starts_on')->get(),
            'errorCount' => $errorCount,
            'canExport' => $this->issues !== null && $errorCount === 0,
            'submissions' => $this->instrumentId === null || $this->academicYearId === null ? collect() : ProjectNationalSubmission::where('school_id', $this->school->id)
                ->where('instrument_id', $this->instrumentId)->where('academic_year_id', $this->academicYearId)
                ->orderByDesc('id')->get(),
        ]);
    }
}
