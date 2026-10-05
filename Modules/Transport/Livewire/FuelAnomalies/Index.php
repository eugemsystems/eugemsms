<?php

declare(strict_types=1);

namespace Modules\Transport\Livewire\FuelAnomalies;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\School;
use Modules\Transport\Domain\Actions\CheckCumulativeFuelAnomalyAction;
use Modules\Transport\Domain\Actions\RecordFuelAnomalyExplanationAction;
use Modules\Transport\Models\FuelLog;

/**
 * `FuelAnomalies\Index` ⭐ (Book H2 OPS-01 §5/§3/BR-OPS-01-014,
 * `transport.fuel.review`). An anomaly is never dismissed without a
 * recorded explanation — `RecordFuelAnomalyExplanationAction` itself
 * enforces this (see its own docblock), this screen's explanation
 * field is the only control that clears one. "Run 30-day check" calls
 * `CheckCumulativeFuelAnomalyAction` on demand — the same honest
 * stand-in this pass uses everywhere a check has no cron wiring yet.
 */
#[Title('Fuel anomalies')]
#[Layout('layouts.app')]
final class Index extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;
    use Toasts;

    /** @var array<int, string> */
    public array $explanation = [];

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('transport.fuel.review');
    }

    public function runCumulativeCheck(): void
    {
        $flagged = app(CheckCumulativeFuelAnomalyAction::class)->execute($this->school->id);
        $this->toast(__(':count newly flagged by the 30-day rolling check.', ['count' => $flagged->count()]));
    }

    public function explain(int $fuelLogId): void
    {
        $explanation = $this->explanation[$fuelLogId] ?? '';

        try {
            app(RecordFuelAnomalyExplanationAction::class)->execute($fuelLogId, $explanation, (int) auth()->id());
        } catch (ValidationException $e) {
            $this->toast(implode(' ', $e->validator->errors()->all()), 'danger');

            return;
        }

        $this->toast(__('Explanation recorded.'));
    }

    public function render(): View
    {
        return view('transport::fuel-anomalies.index', [
            'anomalies' => FuelLog::with('vehicle', 'driver')->where('school_id', $this->school->id)->where('is_anomaly', true)->orderByDesc('fuelled_at')->get(),
        ]);
    }
}
