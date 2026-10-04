<?php

declare(strict_types=1);

namespace Modules\Stores\Livewire\Requisitions;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Core\Domain\Exceptions\DomainException;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\School;
use Modules\Stores\Domain\Actions\RecordRequisitionReturnAction;
use Modules\Stores\Models\StoreRequisition;

/**
 * `Stores\Requisitions\ReturnItems` (Book H1 FIN-09 §7, `inventory.issue`).
 * Named `ReturnItems`, not the spec's own bare `Return` — `return` is a
 * PHP reserved keyword and cannot name a class, the same trap
 * `PPL-04`'s `Exit` (now `ExitProcessing`) and `BRD-08`'s `Case` (now
 * `CaseDetail`) already hit. Reverses at the ORIGINAL issue's own lot
 * costs (BR-FIN-09-008) — this screen never lets the user pick a cost;
 * `RecordRequisitionReturnAction` derives it entirely from the
 * requisition's own issue movements.
 */
#[Title('Record a return')]
#[Layout('layouts.app')]
final class ReturnItems extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;
    use Toasts;

    public ?int $requisitionId = null;

    public ?int $itemId = null;

    public string $returnQuantity = '';

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('inventory.issue');
    }

    public function recordReturn(): void
    {
        $this->validate([
            'requisitionId' => ['required', 'integer'],
            'itemId' => ['required', 'integer'],
            'returnQuantity' => ['required', 'numeric', 'gt:0'],
        ]);

        try {
            app(RecordRequisitionReturnAction::class)->execute(
                (int) $this->requisitionId,
                (int) $this->itemId,
                (float) $this->returnQuantity,
                (int) auth()->id(),
            );
        } catch (ValidationException $e) {
            $this->setErrorBag($e->errors());

            return;
        } catch (DomainException $e) {
            $this->toast($e->getMessage(), 'danger');

            return;
        }

        $this->reset(['returnQuantity']);
        $this->toast(__('Return recorded at the original issue cost.'));
    }

    public function render(): View
    {
        $lines = collect();

        if ($this->requisitionId !== null) {
            $requisition = StoreRequisition::with('lines.item')->find($this->requisitionId);

            if ($requisition !== null) {
                $lines = $requisition->lines;
            }
        }

        return view('stores::requisitions.return', [
            'issued' => StoreRequisition::where('school_id', $this->school->id)
                ->where('status', 'issued')
                ->orderByDesc('issued_at')
                ->limit(100)
                ->get(),
            'lines' => $lines,
        ]);
    }
}
