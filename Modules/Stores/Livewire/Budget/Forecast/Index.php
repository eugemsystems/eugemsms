<?php

declare(strict_types=1);

namespace Modules\Stores\Livewire\Budget\Forecast;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Core\Domain\Support\SessionContext;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Livewire\Sessions\Concerns\InteractsWithSession;
use Modules\Core\Models\AcademicYear;
use Modules\Core\Models\School;
use Modules\Stores\Domain\Actions\CreateForecastAction;
use Modules\Stores\Domain\Actions\ProjectCashFlowAction;
use Modules\Stores\Domain\Actions\ProjectFeeIncomeAction;
use Modules\Stores\Domain\DataObjects\CreateForecastData;
use Modules\Stores\Domain\DataObjects\ProjectForecastData;
use Modules\Stores\Models\Forecast;

/**
 * `Budget\Forecast\Index` (Book H1 FIN-11 §5, `budget.forecast.view`/
 * `.manage`). Folds the spec's three separate screens — Fee income
 * forecast, Cash flow forecast, Scenarios — into one: all three are
 * the same `forecast_type`-tagged row through the same
 * `CreateForecastAction`, which never touches the approved budget
 * (BR-FIN-11-015/AC-FIN-11-006). The projection numbers themselves are
 * entered as a JSON payload here — the real enrolment-/collection-
 * rate-driven computation `BR-FIN-11-013/014` describe is a documented
 * backend gap (`CreateForecastAction`'s own docblock), not built in
 * this pass.
 */
#[Title('Forecasts')]
#[Layout('layouts.app')]
final class Index extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;
    use InteractsWithSession;
    use Toasts;

    public string $forecastType = 'fee_income';

    public string $scenarioName = '';

    public string $assumptionsJson = '{}';

    public string $projectionsJson = '{}';

    public bool $isBaseline = false;

    public ?int $referenceYearId = null;

    public string $currency = 'USD';

    public string $enrolmentGrowthPercent = '0';

    public string $feeIncreasePercent = '0';

    public string $collectionRateOverride = '';

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('budget.forecast.view');
    }

    public function compute(): void
    {
        $this->authorizePermission('budget.forecast.manage');

        if (! in_array($this->forecastType, ['fee_income', 'cash_flow'], true)) {
            $this->toast(__('Only fee-income and cash-flow scenarios can be computed from actuals.'), 'danger');

            return;
        }

        $this->validate([
            'referenceYearId' => ['required', 'integer'],
            'currency' => ['required', 'string', 'size:3'],
            'enrolmentGrowthPercent' => ['required', 'numeric', 'between:-100,500'],
            'feeIncreasePercent' => ['required', 'numeric', 'between:-100,500'],
            'collectionRateOverride' => ['nullable', 'numeric', 'between:0,100'],
        ]);

        $year = AcademicYear::query()->find($this->referenceYearId);

        if ($year === null) {
            $this->addError('referenceYearId', __('Choose a reference academic year.'));

            return;
        }

        $data = new ProjectForecastData(
            schoolId: $this->school->id,
            referenceAcademicYearId: $year->id,
            currency: strtoupper($this->currency),
            enrolmentGrowthPercent: (float) $this->enrolmentGrowthPercent,
            feeIncreasePercent: (float) $this->feeIncreasePercent,
            collectionRatePercentOverride: $this->collectionRateOverride === '' ? null : (float) $this->collectionRateOverride,
        );

        $result = $this->forecastType === 'cash_flow'
            ? app(ProjectCashFlowAction::class)->execute($data)
            : app(ProjectFeeIncomeAction::class)->execute($data);

        $this->assumptionsJson = (string) json_encode($result['assumptions'], JSON_PRETTY_PRINT);
        $this->projectionsJson = (string) json_encode($result['projections'], JSON_PRETTY_PRINT);
        $this->toast(__('Projection computed — review it, name the scenario and save.'));
    }

    public function create(): void
    {
        $this->authorizePermission('budget.forecast.manage');

        $this->validate([
            'scenarioName' => ['required', 'string', 'max:120'],
            'assumptionsJson' => ['required', 'json'],
            'projectionsJson' => ['required', 'json'],
        ]);

        $yearId = SessionContext::yearId();

        if ($yearId === null) {
            $this->toast(__('No current academic year is set.'), 'danger');

            return;
        }

        try {
            app(CreateForecastAction::class)->execute(new CreateForecastData(
                schoolId: $this->school->id,
                academicYearId: $yearId,
                forecastType: $this->forecastType,
                scenarioName: $this->scenarioName,
                assumptions: json_decode($this->assumptionsJson, true) ?? [],
                projections: json_decode($this->projectionsJson, true) ?? [],
                generatedByUserId: (int) auth()->id(),
                isBaseline: $this->isBaseline,
            ));
        } catch (ValidationException $e) {
            $this->setErrorBag($e->errors());

            return;
        }

        $this->reset(['scenarioName', 'assumptionsJson', 'projectionsJson', 'isBaseline']);
        $this->toast(__('Forecast scenario saved — never mistaken for the approved budget.'));
    }

    public function render(): View
    {
        return view('stores::budget.forecast.index', [
            'years' => AcademicYear::query()->orderByDesc('starts_on')->get(['id', 'name']),
            'forecasts' => Forecast::where('school_id', $this->school->id)->orderByDesc('generated_at')->get(),
        ]);
    }
}
