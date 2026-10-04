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
use Modules\Core\Models\School;
use Modules\Stores\Domain\Actions\CreateForecastAction;
use Modules\Stores\Domain\DataObjects\CreateForecastData;
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

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('budget.forecast.view');
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
            'forecasts' => Forecast::where('school_id', $this->school->id)->orderByDesc('generated_at')->get(),
        ]);
    }
}
