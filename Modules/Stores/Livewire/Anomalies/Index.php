<?php

declare(strict_types=1);

namespace Modules\Stores\Livewire\Anomalies;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\School;
use Modules\Stores\Domain\Actions\ComputeConsumptionBaselineAction;
use Modules\Stores\Domain\Actions\DetectConsumptionAnomalyAction;
use Modules\Stores\Domain\Actions\RecordAnomalyInvestigationAction;
use Modules\Stores\Models\ConsumptionAnomaly;
use Modules\Stores\Models\InventoryItem;
use Modules\Stores\Models\Store;

/**
 * `Stores\Anomalies\Index` (Book H1 FIN-09 §7 ⭐, `inventory.anomaly.review`).
 * An anomaly is never auto-dismissed — `RecordAnomalyInvestigationAction`
 * itself refuses an empty note (BR-FIN-09-023), and this screen has no
 * "dismiss" control that skips it. Also hosts baseline computation
 * (`ComputeConsumptionBaselineAction`), since a baseline must exist
 * before detection can find anything.
 */
#[Title('Consumption anomalies')]
#[Layout('layouts.app')]
final class Index extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;
    use Toasts;

    public ?int $storeId = null;

    public ?int $itemId = null;

    public string $periodType = 'weekly';

    public int $baselineDays = 60;

    public string $periodStart;

    public string $periodEnd;

    /** @var array<int, string> anomalyId => note */
    public array $notes = [];

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('inventory.anomaly.review');
        $this->periodStart = now()->subDays(7)->toDateString();
        $this->periodEnd = now()->toDateString();
    }

    public function computeBaseline(): void
    {
        $this->validate(['storeId' => ['required', 'integer'], 'itemId' => ['required', 'integer']]);

        app(ComputeConsumptionBaselineAction::class)->execute(
            (int) $this->storeId,
            (int) $this->itemId,
            $this->periodType,
            $this->baselineDays,
        );

        $this->toast(__('Baseline computed.'));
    }

    public function detect(): void
    {
        $this->validate(['storeId' => ['required', 'integer'], 'itemId' => ['required', 'integer']]);

        $anomaly = app(DetectConsumptionAnomalyAction::class)->execute(
            (int) $this->storeId,
            (int) $this->itemId,
            Carbon::parse($this->periodStart),
            Carbon::parse($this->periodEnd),
        );

        $this->toast($anomaly !== null ? __('Anomaly flagged — :p% variance.', ['p' => $anomaly->variance_percent]) : __('No anomaly — within tolerance (or no baseline yet).'));
    }

    public function investigate(int $anomalyId, bool $explained): void
    {
        $note = $this->notes[$anomalyId] ?? '';

        try {
            app(RecordAnomalyInvestigationAction::class)->execute($anomalyId, $note, (int) auth()->id(), $explained);
        } catch (ValidationException $e) {
            $this->toast(implode(' ', $e->validator->errors()->all()), 'danger');

            return;
        }

        $this->toast(__('Investigation recorded.'));
    }

    public function render(): View
    {
        return view('stores::anomalies.index', [
            'stores' => Store::where('school_id', $this->school->id)->orderBy('code')->get(),
            'items' => InventoryItem::where('school_id', $this->school->id)->orderBy('name')->limit(300)->get(),
            'anomalies' => ConsumptionAnomaly::with('item', 'store')
                ->where('school_id', $this->school->id)
                ->whereIn('status', ['open', 'escalated'])
                ->orderByDesc('detected_at')
                ->get(),
        ]);
    }
}
