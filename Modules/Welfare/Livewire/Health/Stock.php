<?php

declare(strict_types=1);

namespace Modules\Welfare\Livewire\Health;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Core\Domain\Exceptions\DomainException;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\School;
use Modules\Welfare\Domain\Actions\ReceiveClinicStockAction;
use Modules\Welfare\Models\ClinicStock;
use Modules\Welfare\Models\ControlledStockLogEntry;

/**
 * `Health\Stock` (Book G BRD-06 §5, `health.stock.manage` — receive;
 * `health.stock.controlled` ⚠⚠ — controlled stock). Folds the spec's
 * separate "Controlled register" screen into one, since the controlled
 * log is simply a filtered view of the same stock catalogue's own
 * append-only trail — no separate Action or model relationship would be
 * saved by splitting it out.
 */
#[Title('Clinic stock')]
#[Layout('layouts.app')]
final class Stock extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;
    use Toasts;

    public ?int $receivingStockId = null;

    public float $quantity = 1.0;

    public ?int $witnessedByUserId = null;

    public ?string $batchNumber = null;

    public ?string $expiryDate = null;

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('health.stock.manage');
    }

    public function receive(int $stockId): void
    {
        $stock = ClinicStock::findOrFail($stockId);

        if ($stock->is_controlled) {
            $this->authorizePermission('health.stock.controlled');
        }

        try {
            app(ReceiveClinicStockAction::class)->execute(
                clinicStockId: $stockId,
                quantity: $this->quantity,
                performedByUserId: (int) Auth::id(),
                witnessedByUserId: $this->witnessedByUserId,
                batchNumber: $this->batchNumber,
                expiryDate: $this->expiryDate,
            );
        } catch (DomainException $e) {
            $this->toast($e->getMessage(), 'danger');

            return;
        }

        $this->reset(['receivingStockId', 'witnessedByUserId', 'batchNumber', 'expiryDate']);
        $this->quantity = 1.0;
        $this->toast(__('Stock received.'));
    }

    public function render(): View
    {
        return view('welfare::health.stock', [
            'stock' => ClinicStock::where('school_id', $this->school->id)->orderBy('name')->get(),
            'controlledLog' => ControlledStockLogEntry::where('school_id', $this->school->id)->orderByDesc('occurred_at')->limit(50)->get(),
        ]);
    }
}
