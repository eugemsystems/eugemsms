<?php

declare(strict_types=1);

namespace Modules\Stores\Livewire\StockTake;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Core\Domain\Support\SessionContext;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Livewire\Sessions\Concerns\InteractsWithSession;
use Modules\Core\Models\School;
use Modules\Stores\Domain\Actions\CreateStockTakeAction;
use Modules\Stores\Domain\Actions\GetBlindCountSheetAction;
use Modules\Stores\Domain\Actions\SubmitStockCountAction;
use Modules\Stores\Domain\DataObjects\CreateStockTakeData;
use Modules\Stores\Models\StockTake;
use Modules\Stores\Models\Store;

/**
 * `Stores\StockTake\Count` (Book H1 FIN-09 §7 ⭐, `inventory.stocktake.count`).
 * The blind-count discipline is structural, not a UI choice: `$sheet`
 * below is built exclusively from `GetBlindCountSheetAction`'s own
 * `BlindCountLine` DTO, which has no field to carry `system_quantity`
 * even if this component tried to show it (AC-FIN-09-005/BR-FIN-09-014).
 */
#[Title('Stock take — count')]
#[Layout('layouts.app')]
final class Count extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;
    use InteractsWithSession;
    use Toasts;

    public ?int $storeId = null;

    public string $takeType = 'full';

    public ?int $activeTakeId = null;

    /** @var array<int, string> lineId => counted quantity */
    public array $counts = [];

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->loadSessionContext($school);
        $this->authorizePermission('inventory.stocktake.count');
    }

    public function createTake(): void
    {
        $this->validate(['storeId' => ['required', 'integer']]);

        $termId = SessionContext::termId();

        if ($termId === null) {
            $this->toast(__('No current term is set.'), 'danger');

            return;
        }

        $take = app(CreateStockTakeAction::class)->execute(new CreateStockTakeData(
            schoolId: $this->school->id,
            termId: $termId,
            storeId: (int) $this->storeId,
            takeType: $this->takeType,
            scheduledFor: Carbon::now(),
        ));

        $this->activeTakeId = $take->id;
        $this->toast(__('Stock take started — :n lines to count, blind.', ['n' => $take->line_count]));
    }

    public function submitCount(int $lineId): void
    {
        $value = $this->counts[$lineId] ?? null;

        if ($value === null || $value === '') {
            return;
        }

        app(SubmitStockCountAction::class)->execute($lineId, (float) $value, (int) auth()->id());
        $this->toast(__('Count recorded.'));
    }

    public function render(): View
    {
        $sheet = $this->activeTakeId !== null
            ? app(GetBlindCountSheetAction::class)->execute($this->activeTakeId)
            : collect();

        return view('stores::stocktake.count', [
            'stores' => Store::where('school_id', $this->school->id)->orderBy('code')->get(),
            'openTakes' => StockTake::where('school_id', $this->school->id)->where('status', 'counting')->get(),
            'sheet' => $sheet,
        ]);
    }
}
