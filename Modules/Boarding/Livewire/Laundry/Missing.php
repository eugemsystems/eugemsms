<?php

declare(strict_types=1);

namespace Modules\Boarding\Livewire\Laundry;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Boarding\Domain\Actions\ResolveLaundryDiscrepancyAction;
use Modules\Boarding\Domain\DataObjects\ResolveLaundryDiscrepancyData;
use Modules\Boarding\Models\LaundryItem;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\School;
use Modules\Finance\Models\FeeComponent;

/**
 * `Laundry\Missing` (Book F BRD-05 §4, `linen.manage`). Every open
 * discrepancy across every cycle, resolved either without charge or
 * with a `FIN-02` ad hoc charge — `ReconcileLaundryCycleAction` won't
 * let a cycle close while a row here stays unresolved.
 */
#[Title('Missing laundry items')]
#[Layout('layouts.app')]
final class Missing extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;
    use Toasts;

    public ?int $feeComponentId = null;

    public ?int $chargeAmountMinor = null;

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('boarding.linen.manage');
    }

    public function resolveWithoutCharge(int $laundryItemId): void
    {
        app(ResolveLaundryDiscrepancyAction::class)->execute(new ResolveLaundryDiscrepancyData(
            laundryItemId: $laundryItemId,
            resolution: 'found',
        ));

        $this->toast(__('Resolved — item found.'));
    }

    public function resolveWithCharge(int $laundryItemId): void
    {
        if ($this->feeComponentId === null || $this->chargeAmountMinor === null) {
            $this->toast(__('A fee component and amount are required.'), 'danger');

            return;
        }

        app(ResolveLaundryDiscrepancyAction::class)->execute(new ResolveLaundryDiscrepancyData(
            laundryItemId: $laundryItemId,
            resolution: 'charged',
            feeComponentId: $this->feeComponentId,
            chargeAmountMinor: $this->chargeAmountMinor,
            approvedByUserId: (int) Auth::id(),
        ));

        $this->reset(['feeComponentId', 'chargeAmountMinor']);
        $this->toast(__('Resolved with charge.'));
    }

    public function render(): View
    {
        return view('boarding::laundry.missing', [
            'discrepancies' => LaundryItem::where('school_id', $this->school->id)
                ->where('resolved', false)
                ->whereNotNull('items_back')
                ->whereColumn('items_back', '<', 'items_out')
                ->with('student', 'cycle.hostel')
                ->get(),
            'feeComponents' => FeeComponent::where('school_id', $this->school->id)->orderBy('name')->get(['id', 'name']),
        ]);
    }
}
