<?php

declare(strict_types=1);

namespace Modules\Boarding\Livewire\Laundry;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Boarding\Domain\Actions\CollectLaundryAction;
use Modules\Boarding\Domain\Actions\CreateLaundryCycleAction;
use Modules\Boarding\Domain\Actions\ReconcileLaundryCycleAction;
use Modules\Boarding\Domain\Actions\RecordLaundryReturnAction;
use Modules\Boarding\Domain\DataObjects\CollectLaundryData;
use Modules\Boarding\Domain\DataObjects\CreateLaundryCycleData;
use Modules\Boarding\Domain\DataObjects\ReconcileLaundryCycleData;
use Modules\Boarding\Domain\DataObjects\RecordLaundryReturnData;
use Modules\Boarding\Models\BedAllocation;
use Modules\Boarding\Models\Hostel;
use Modules\Boarding\Models\LaundryCycle;
use Modules\Core\Domain\Exceptions\DomainException;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Livewire\Sessions\Concerns\InteractsWithSession;
use Modules\Core\Models\School;

/**
 * `Laundry\Cycles` (Book F BRD-05 §4, `linen.manage`). Create → collect
 * (one row per currently-allocated learner in the hostel, defaulting
 * to 1 item out — adjustable before saving) → return → reconcile.
 * `ReconcileLaundryCycleAction` itself refuses while any discrepancy
 * is still open (BR-BRD-05-005) — this screen shows that refusal as a
 * hard toast, not a soft warning with a way past it.
 */
#[Title('Laundry cycles')]
#[Layout('layouts.app')]
final class Cycles extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;
    use InteractsWithSession;
    use Toasts;

    public ?int $hostelId = null;

    public string $cycleDate = '';

    public ?int $activeCycleId = null;

    /** @var array<int, int> */
    public array $itemsOut = [];

    /** @var array<int, int> */
    public array $itemsBack = [];

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('boarding.linen.view');
        $this->cycleDate = now()->toDateString();
    }

    public function createCycle(): void
    {
        $this->authorizePermission('boarding.linen.manage');

        $term = $this->school->currentAcademicYear()?->currentTerm();

        if ($term === null || $this->hostelId === null) {
            $this->toast(__('A hostel and current term are required.'), 'danger');

            return;
        }

        $cycle = app(CreateLaundryCycleAction::class)->execute(new CreateLaundryCycleData(
            schoolId: $this->school->id,
            termId: $term->id,
            hostelId: $this->hostelId,
            cycleDate: Carbon::parse($this->cycleDate),
        ));

        $this->activeCycleId = $cycle->id;
        $this->toast(__('Laundry cycle created.'));
    }

    public function collect(): void
    {
        $this->authorizePermission('boarding.linen.manage');

        if ($this->activeCycleId === null) {
            return;
        }

        $items = [];

        foreach ($this->itemsOut as $studentId => $count) {
            if ($count > 0) {
                $items[] = ['studentId' => $studentId, 'itemsOut' => $count];
            }
        }

        try {
            app(CollectLaundryAction::class)->execute(new CollectLaundryData(
                laundryCycleId: $this->activeCycleId,
                collectedAt: Carbon::now(),
                items: $items,
            ));
        } catch (DomainException $e) {
            $this->toast($e->getMessage(), 'danger');

            return;
        }

        $this->toast(__('Collection recorded.'));
    }

    public function recordReturn(): void
    {
        $this->authorizePermission('boarding.linen.manage');

        if ($this->activeCycleId === null) {
            return;
        }

        $cycle = LaundryCycle::with('items')->findOrFail($this->activeCycleId);
        $items = [];

        foreach ($cycle->items as $item) {
            $items[] = [
                'studentId' => $item->student_id,
                'itemsBack' => $this->itemsBack[$item->student_id] ?? $item->items_out,
                'missingDescription' => null,
            ];
        }

        try {
            app(RecordLaundryReturnAction::class)->execute(new RecordLaundryReturnData(
                laundryCycleId: $this->activeCycleId,
                returnedAt: Carbon::now(),
                items: $items,
            ));
        } catch (DomainException $e) {
            $this->toast($e->getMessage(), 'danger');

            return;
        }

        $this->toast(__('Return recorded.'));
    }

    public function reconcile(): void
    {
        $this->authorizePermission('boarding.linen.manage');

        if ($this->activeCycleId === null) {
            return;
        }

        try {
            app(ReconcileLaundryCycleAction::class)->execute(new ReconcileLaundryCycleData(
                laundryCycleId: $this->activeCycleId,
            ));
        } catch (DomainException $e) {
            $this->toast($e->getMessage(), 'danger');

            return;
        }

        $this->toast(__('Cycle reconciled.'));
    }

    public function render(): View
    {
        $cycle = $this->activeCycleId !== null ? LaundryCycle::with('items.student')->find($this->activeCycleId) : null;

        $roster = $this->hostelId !== null
            ? BedAllocation::where('hostel_id', $this->hostelId)->where('status', 'confirmed')->whereNull('effective_to')->with('student')->get()
            : collect();

        return view('boarding::laundry.cycles', [
            'hostels' => Hostel::where('school_id', $this->school->id)->orderBy('name')->get(),
            'cycle' => $cycle,
            'roster' => $roster,
            'recentCycles' => LaundryCycle::where('school_id', $this->school->id)->with('hostel')->orderByDesc('cycle_date')->limit(20)->get(),
        ]);
    }
}
