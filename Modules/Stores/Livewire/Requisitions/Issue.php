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
use Modules\Stores\Domain\Actions\ApproveStoreRequisitionAction;
use Modules\Stores\Domain\Actions\IssueStockAction;
use Modules\Stores\Models\StoreRequisition;

/**
 * `Stores\Requisitions\Issue` (Book H1 FIN-09 §7, `inventory.issue` —
 * approve requires `inventory.requisition.approve`). One lifecycle
 * screen hosting both steps — approve (with per-line quantity
 * overrides for a partial approval) then issue — matching this
 * codebase's established "one screen, whole action bar" shape
 * (`People\Admissions\Applications\Show`). `IssueStockAction` itself
 * runs the real FIFO consumption and posts the one aggregated journal
 * per requisition (BR-FIN-09-004/006/007); this screen surfaces its
 * refusal (insufficient stock, locked period) as a toast, never a
 * client-side guess.
 */
#[Title('Issue requisitions')]
#[Layout('layouts.app')]
final class Issue extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;
    use Toasts;

    /** @var array<int, array<int, string>> requisitionId => [lineId => quantity] */
    public array $overrides = [];

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('inventory.issue');
    }

    public function approve(int $requisitionId): void
    {
        $this->authorizePermission('inventory.requisition.approve');

        $lineOverrides = array_filter(array_map(
            fn (string $q): ?float => $q !== '' ? (float) $q : null,
            $this->overrides[$requisitionId] ?? [],
        ), fn (?float $q): bool => $q !== null);

        try {
            app(ApproveStoreRequisitionAction::class)->execute($requisitionId, (int) auth()->id(), $lineOverrides);
        } catch (DomainException $e) {
            $this->toast($e->getMessage(), 'danger');

            return;
        }

        $this->toast(__('Requisition approved.'));
    }

    public function issue(int $requisitionId): void
    {
        try {
            app(IssueStockAction::class)->execute($requisitionId, (int) auth()->id());
        } catch (ValidationException $e) {
            $this->toast(implode(' ', $e->validator->errors()->all()), 'danger');

            return;
        } catch (DomainException $e) {
            $this->toast($e->getMessage(), 'danger');

            return;
        }

        $this->toast(__('Stock issued — one journal posted.'));
    }

    public function render(): View
    {
        return view('stores::requisitions.issue', [
            'pending' => StoreRequisition::with('lines.item', 'store')
                ->where('school_id', $this->school->id)
                ->where('status', 'pending')
                ->orderBy('required_by')
                ->get(),
            'approved' => StoreRequisition::with('lines.item', 'store')
                ->where('school_id', $this->school->id)
                ->where('status', 'approved')
                ->orderBy('required_by')
                ->get(),
        ]);
    }
}
