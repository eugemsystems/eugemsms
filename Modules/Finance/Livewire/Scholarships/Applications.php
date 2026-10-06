<?php

declare(strict_types=1);

namespace Modules\Finance\Livewire\Scholarships;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use InvalidArgumentException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Core\Domain\Exceptions\DomainException;
use Modules\Core\Domain\Support\Auth\PermissionScope;
use Modules\Core\Domain\Support\Auth\PermissionScopeResolver;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\AcademicYear;
use Modules\Core\Models\School;
use Modules\Finance\Domain\Actions\SubmitScholarshipApplicationAction;
use Modules\Finance\Domain\DataObjects\SubmitScholarshipApplicationData;
use Modules\Finance\Livewire\Concerns\SearchesStudents;
use Modules\Finance\Models\DiscountScheme;
use Modules\Finance\Models\ScholarshipApplication;
use Modules\People\Models\Student;

/**
 * `Finance\Scholarships\Applications` (Book K FIN-07 §5,
 * `finance.scholarship.review`). Applications with their means data. A
 * reviewer with `finance.scholarship.apply` can also submit one on a
 * family's behalf. Means and income data is only listed to holders of the
 * review permission — anyone else cannot even open the screen — and
 * supporting documents are shown as a count, opened from the file vault
 * under that module's own access rules. The academic average on file is
 * what was stated at application; renewal checks use recorded results.
 */
#[Title('Scholarship applications')]
#[Layout('layouts.app')]
final class Applications extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;
    use SearchesStudents;
    use Toasts;

    public string $statusFilter = '';

    public ?int $schemeId = null;

    public ?int $academicYearId = null;

    public string $incomeBand = '';

    public string $meansScore = '';

    public string $academicAverage = '';

    public string $narrative = '';

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('finance.scholarship.review');

        $this->academicYearId = AcademicYear::query()->orderByDesc('starts_on')->value('id');
    }

    public function submit(): void
    {
        $this->authorizePermission('finance.scholarship.apply');
        $this->resetErrorBag();

        $this->validate([
            'schemeId' => ['required', 'integer'], 'academicYearId' => ['required', 'integer'],
            'incomeBand' => ['nullable', 'string', 'max:30'], 'meansScore' => ['nullable', 'numeric', 'between:0,100'],
            'academicAverage' => ['nullable', 'numeric', 'between:0,100'], 'narrative' => ['nullable', 'string', 'max:5000'],
        ]);

        $student = $this->selectedStudent();

        if ($student === null) {
            $this->addError('selectedStudentId', __('Choose the learner first.'));

            return;
        }

        try {
            app(SubmitScholarshipApplicationAction::class)->execute(new SubmitScholarshipApplicationData(
                schoolId: $this->school->id, academicYearId: (int) $this->academicYearId, schemeId: (int) $this->schemeId, studentId: $student->id,
                householdIncomeBand: $this->incomeBand === '' ? null : $this->incomeBand,
                meansAssessmentScore: $this->meansScore === '' ? null : $this->meansScore,
                academicAverageAtApplication: $this->academicAverage === '' ? null : $this->academicAverage,
                narrative: $this->narrative === '' ? null : $this->narrative,
            ));
        } catch (InvalidArgumentException|DomainException $exception) {
            $this->addError('schemeId', $exception->getMessage());

            return;
        }

        $this->reset('schemeId', 'incomeBand', 'meansScore', 'academicAverage', 'narrative', 'selectedStudentId', 'selectedStudentLabel');
        $this->toast(__('Application submitted.'));
    }

    public function render(): View
    {
        $user = auth()->user();
        $applications = ScholarshipApplication::query()
            ->when(in_array($this->statusFilter, ['submitted', 'under_review', 'committee_review', 'approved', 'rejected', 'waitlisted'], true), fn ($q) => $q->where('status', $this->statusFilter))
            ->orderByDesc('id')->limit(200)->get();

        return view('finance::scholarships.applications', [
            'applications' => $applications,
            'students' => Student::query()->whereIn('id', $applications->pluck('student_id'))->get()->keyBy('id'),
            'schemeNames' => DiscountScheme::query()->whereIn('id', $applications->pluck('scheme_id'))->pluck('name', 'id'),
            'schemes' => DiscountScheme::query()->where('scheme_type', 'application_based')->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'years' => AcademicYear::query()->orderByDesc('starts_on')->limit(8)->get(['id', 'name']),
            'results' => $this->studentResults(),
            'canApply' => $user !== null && app(PermissionScopeResolver::class)->has($user, 'finance.scholarship.apply', PermissionScope::Own),
        ]);
    }
}
