<?php

declare(strict_types=1);

namespace Modules\Finance\Livewire\Discounts;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use InvalidArgumentException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Core\Domain\Exceptions\DomainException;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\AcademicYear;
use Modules\Core\Models\School;
use Modules\Core\Models\Term;
use Modules\Finance\Domain\Actions\GrantAwardAction;
use Modules\Finance\Domain\Actions\PreviewAwardEnvelopeAction;
use Modules\Finance\Domain\DataObjects\GrantAwardData;
use Modules\Finance\Livewire\Concerns\SearchesStudents;
use Modules\Finance\Models\DiscountScheme;
use Modules\Finance\Models\FeeComponent;
use Modules\Finance\Models\ScholarshipApplication;
use Modules\People\Models\Guardian;

/**
 * `Finance\Awards\Grant` (Book K FIN-07 §5, `finance.award.grant` ⚠). Grants
 * an award, showing the scheme's budget envelope live before submission —
 * the same check `GrantAwardAction` enforces, so an over-commitment is
 * refused naming its shortfall (BR-FIN-07-009). An application-based scheme
 * needs an approved application behind it; an award above the approval
 * threshold waits for CORE-07 approval; a sponsor-funded scheme needs its
 * sponsor and bills them rather than discounting (BR-FIN-07-010).
 */
#[Title('Grant award')]
#[Layout('layouts.app')]
final class GrantAward extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;
    use SearchesStudents;
    use Toasts;

    public ?int $schemeId = null;

    public ?int $academicYearId = null;

    public ?int $termId = null;

    public string $awardMethod = 'percentage';

    public string $percent = '';

    public string $amount = '';

    public string $currency = 'USD';

    /** @var array<int, int> */
    public array $componentIds = [];

    public ?int $applicationId = null;

    public ?int $sponsorGuardianId = null;

    public string $conditionNote = '';

    public string $effectiveFrom = '';

    public string $sponsorSearch = '';

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('finance.award.grant');

        $this->academicYearId = AcademicYear::query()->orderByDesc('starts_on')->value('id');
        $this->effectiveFrom = now()->toDateString();
    }

    public function selectSponsor(int $guardianId): void
    {
        $this->sponsorGuardianId = Guardian::query()->findOrFail($guardianId)->id;
        $this->sponsorSearch = '';
    }

    public function grant(): void
    {
        $this->authorizePermission('finance.award.grant');
        $this->resetErrorBag();

        $this->validate([
            'schemeId' => ['required', 'integer'], 'academicYearId' => ['required', 'integer'], 'termId' => ['nullable', 'integer'],
            'awardMethod' => ['required', 'in:percentage,fixed_amount'], 'percent' => ['nullable', 'numeric', 'between:0,100'],
            'amount' => ['nullable', 'numeric', 'min:0'], 'effectiveFrom' => ['required', 'date'], 'conditionNote' => ['nullable', 'string', 'max:255'],
        ]);

        $student = $this->selectedStudent();

        if ($student === null) {
            $this->addError('selectedStudentId', __('Choose the learner first.'));

            return;
        }

        try {
            $award = app(GrantAwardAction::class)->execute(new GrantAwardData(
                schoolId: $this->school->id, schemeId: (int) $this->schemeId, studentId: $student->id, academicYearId: (int) $this->academicYearId,
                grantedByUserId: (int) auth()->id(), awardMethod: $this->awardMethod, effectiveFrom: Carbon::parse($this->effectiveFrom),
                applicationId: $this->applicationId, termId: $this->termId,
                appliesToComponents: $this->componentIds === [] ? null : array_map('intval', $this->componentIds),
                awardPercent: $this->awardMethod === 'percentage' ? $this->percent : null,
                awardAmountMinor: $this->awardMethod === 'fixed_amount' ? (int) round((float) $this->amount * 100) : null,
                currency: $this->awardMethod === 'fixed_amount' ? strtoupper($this->currency) : null,
                sponsorGuardianId: $this->sponsorGuardianId,
                conditionNote: $this->conditionNote === '' ? null : $this->conditionNote,
            ));
        } catch (InvalidArgumentException|DomainException $exception) {
            $this->addError('schemeId', $exception->getMessage());

            return;
        }

        $this->reset('percent', 'amount', 'componentIds', 'applicationId', 'sponsorGuardianId', 'conditionNote', 'selectedStudentId', 'selectedStudentLabel');
        $this->toast($award->status === 'pending_approval' ? __('Award granted — waiting for approval before it takes effect.') : __('Award granted.'));
    }

    public function render(): View
    {
        $scheme = $this->schemeId === null ? null : DiscountScheme::query()->find($this->schemeId);
        $preview = $scheme === null || $this->academicYearId === null ? null : app(PreviewAwardEnvelopeAction::class)->execute(
            $this->school->id, $scheme->id, $this->academicYearId,
            $this->awardMethod === 'fixed_amount' && $this->amount !== '' && is_numeric($this->amount) ? (int) round((float) $this->amount * 100) : null,
        );

        $sponsorTerm = trim($this->sponsorSearch);

        return view('finance::discounts.grant', [
            'schemes' => DiscountScheme::query()->where('is_active', true)->where('scheme_type', '!=', 'automatic')->orderBy('name')->get(['id', 'code', 'name', 'scheme_type', 'is_sponsor_funded']),
            'scheme' => $scheme,
            'years' => AcademicYear::query()->orderByDesc('starts_on')->limit(8)->get(['id', 'name']),
            'terms' => $this->academicYearId === null ? collect() : Term::query()->where('academic_year_id', $this->academicYearId)->orderBy('starts_on')->get(['id', 'name']),
            'components' => FeeComponent::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'applications' => $scheme === null || $this->selectedStudentId === null ? collect() : ScholarshipApplication::query()->where('scheme_id', $scheme->id)->where('student_id', $this->selectedStudentId)->where('status', 'approved')->get(['id', 'academic_year_id']),
            'sponsors' => mb_strlen($sponsorTerm) < 2 ? collect() : Guardian::query()->where(fn ($q) => $q->where('first_name', 'like', '%'.addcslashes($sponsorTerm, '%_\\').'%')->orWhere('last_name', 'like', '%'.addcslashes($sponsorTerm, '%_\\').'%')->orWhere('organisation_name', 'like', '%'.addcslashes($sponsorTerm, '%_\\').'%'))->limit(10)->get(),
            'sponsor' => $this->sponsorGuardianId === null ? null : Guardian::query()->find($this->sponsorGuardianId),
            'results' => $this->studentResults(),
            'preview' => $preview,
        ]);
    }
}
