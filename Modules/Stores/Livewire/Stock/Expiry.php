<?php

declare(strict_types=1);

namespace Modules\Stores\Livewire\Stock;

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
use Modules\Finance\Models\Account;
use Modules\Stores\Domain\Actions\CheckExpiringLotsAction;
use Modules\Stores\Domain\Actions\WriteOffExpiredStockAction;
use Modules\Stores\Models\StockLot;

/**
 * `Stores\Stock\Expiry` (Book H1 FIN-09 §7, `inventory.stock.view` to
 * view, `inventory.adjustment.post` ⚠ to write off). Lists approaching
 * expiry via a live `CheckExpiringLotsAction` run and already-expired
 * lots directly — `StockCostingEngine::consume()` itself already
 * refuses to issue an expired lot (BR-FIN-09-011); the only path off
 * the books is the write-off below, at the lot's own cost, to a
 * wastage account.
 */
#[Title('Expiry monitor')]
#[Layout('layouts.app')]
final class Expiry extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;
    use InteractsWithSession;
    use Toasts;

    public ?int $wastageAccountId = null;

    /** @var array<int, string> lotId => write-off quantity */
    public array $writeOffQuantities = [];

    /** @var array<int, string> lotId => reason */
    public array $writeOffReasons = [];

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->loadSessionContext($school);
        $this->authorizePermission('inventory.stock.view');
    }

    public function writeOff(int $lotId): void
    {
        $this->authorizePermission('inventory.adjustment.post');

        $quantity = (float) ($this->writeOffQuantities[$lotId] ?? 0);
        $reason = $this->writeOffReasons[$lotId] ?? '';
        $termId = SessionContext::termId();
        $yearId = SessionContext::yearId();

        if ($termId === null || $yearId === null || $this->wastageAccountId === null) {
            $this->toast(__('A current term and wastage account are both required.'), 'danger');

            return;
        }

        try {
            app(WriteOffExpiredStockAction::class)->execute(
                $lotId,
                $quantity,
                (int) $this->wastageAccountId,
                (int) auth()->id(),
                $yearId,
                $termId,
                $reason,
            );
        } catch (ValidationException $e) {
            $this->toast(implode(' ', $e->validator->errors()->all()), 'danger');

            return;
        }

        $this->toast(__('Expired stock written off.'));
    }

    public function render(): View
    {
        return view('stores::stock.expiry', [
            'approaching' => app(CheckExpiringLotsAction::class)->execute($this->school->id),
            'expired' => StockLot::with('item', 'store')
                ->where('school_id', $this->school->id)
                ->where('is_depleted', false)
                ->whereNotNull('expiry_date')
                ->whereDate('expiry_date', '<', now()->toDateString())
                ->get(),
            'accounts' => Account::where('school_id', $this->school->id)->where('is_postable', true)->orderBy('code')->get(),
        ]);
    }
}
