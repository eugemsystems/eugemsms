<?php

declare(strict_types=1);

namespace Modules\Finance\Livewire\Discounts;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use InvalidArgumentException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\AcademicYear;
use Modules\Core\Models\School;
use Modules\Finance\Domain\Actions\CreateBudgetEnvelopeAction;
use Modules\Finance\Domain\DataObjects\CreateBudgetEnvelopeData;
use Modules\Finance\Models\DiscountScheme;
use Modules\Finance\Models\SchemeBudgetEnvelope;

/**
 * `Finance\Discounts\Budgets` (Book K FIN-07 §5,
 * `finance.discount_scheme.manage`). Per-scheme, per-year budget envelopes
 * with what is committed (computed into a draft billing run), utilised
 * (posted) and still available. A blank budget is uncapped — the right
 * setting for sibling and staff-child schemes the school commits to
 * unconditionally.
 */
#[Title('Budget envelopes')]
#[Layout('layouts.app')]
final class Budgets extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;
    use Toasts;

    public ?int $schemeId = null;

    public ?int $academicYearId = null;

    public string $budget = '';

    public string $currency = 'USD';

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('finance.discount_scheme.manage');

        $this->academicYearId = AcademicYear::query()->orderByDesc('starts_on')->value('id');
    }

    public function create(): void
    {
        $this->authorizePermission('finance.discount_scheme.manage');
        $this->resetErrorBag();

        $this->validate(['schemeId' => ['required', 'integer'], 'academicYearId' => ['required', 'integer'], 'budget' => ['nullable', 'numeric', 'min:0']]);

        try {
            app(CreateBudgetEnvelopeAction::class)->execute(new CreateBudgetEnvelopeData(
                schoolId: $this->school->id, schemeId: (int) $this->schemeId, academicYearId: (int) $this->academicYearId,
                budgetMinor: $this->budget === '' ? null : (int) round((float) $this->budget * 100),
                currency: $this->budget === '' ? null : strtoupper($this->currency),
            ));
        } catch (InvalidArgumentException $exception) {
            $this->addError('schemeId', $exception->getMessage());

            return;
        }

        $this->reset('schemeId', 'budget');
        $this->toast(__('Envelope created.'));
    }

    public function render(): View
    {
        return view('finance::discounts.budgets', [
            'envelopes' => SchemeBudgetEnvelope::query()->with('scheme')->when($this->academicYearId !== null, fn ($q) => $q->where('academic_year_id', $this->academicYearId))->orderBy('scheme_id')->get(),
            'schemes' => DiscountScheme::query()->where('is_active', true)->orderBy('code')->get(['id', 'code', 'name']),
            'years' => AcademicYear::query()->orderByDesc('starts_on')->limit(8)->get(['id', 'name']),
        ]);
    }
}
