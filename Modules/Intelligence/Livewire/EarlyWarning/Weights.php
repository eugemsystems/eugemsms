<?php

declare(strict_types=1);

namespace Modules\Intelligence\Livewire\EarlyWarning;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use InvalidArgumentException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\School;
use Modules\Intelligence\Domain\Actions\SetRiskScoreWeightAction;
use Modules\Intelligence\Domain\Registry\RiskIndicatorRegistry;
use Modules\Intelligence\Models\RiskScoreWeight;

/**
 * `Intelligence\EarlyWarning\Weights` (Book J INT-03 §5,
 * `risk.configure`). A school re-weights or disables the learner
 * indicators the owning modules registered — it cannot add one
 * (BR-INT-03-003); the indicator key is checked against the registry
 * by the Action, not trusted from the form. Changes apply from the
 * next recompute and never rewrite past scores.
 */
#[Title('Indicator weights')]
#[Layout('layouts.app')]
final class Weights extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;
    use Toasts;

    /** @var array<string, array{weight: string, enabled: bool}> */
    public array $rows = [];

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('risk.configure');

        $this->loadRows();
    }

    public function save(string $indicatorKey): void
    {
        $this->authorizePermission('risk.configure');
        $this->resetErrorBag();

        $row = $this->rows[$indicatorKey] ?? null;
        abort_unless($row !== null && RiskIndicatorRegistry::get($indicatorKey) !== null, 404);

        if (! is_numeric($row['weight'])) {
            $this->addError("rows.{$indicatorKey}.weight", __('Enter a weight between 0 and 100.'));

            return;
        }

        try {
            app(SetRiskScoreWeightAction::class)->execute($this->school->id, $indicatorKey, (float) $row['weight'], (bool) $row['enabled']);
        } catch (InvalidArgumentException $exception) {
            $this->addError("rows.{$indicatorKey}.weight", $exception->getMessage());

            return;
        }

        $this->toast(__('Weight saved — applies from the next recompute.'));
    }

    private function loadRows(): void
    {
        $overrides = RiskScoreWeight::where('school_id', $this->school->id)->get()->keyBy('indicator_key');

        $this->rows = [];

        foreach (RiskIndicatorRegistry::forAppliesTo('learner') as $indicator) {
            $override = $overrides->get($indicator->key);

            $this->rows[$indicator->key] = [
                'weight' => (string) ($override === null ? $indicator->defaultWeight : $override->weight),
                'enabled' => $override === null ? true : $override->is_enabled,
            ];
        }
    }

    public function render(): View
    {
        return view('intelligence::early-warning.weights', [
            'indicators' => collect(RiskIndicatorRegistry::forAppliesTo('learner')),
        ]);
    }
}
