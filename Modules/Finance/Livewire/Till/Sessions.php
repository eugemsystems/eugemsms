<?php

declare(strict_types=1);

namespace Modules\Finance\Livewire\Till;

use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\School;
use Modules\Finance\Models\Till;
use Modules\Finance\Models\TillSession;

/**
 * `Finance\Till\Sessions` (Book B FIN-04 §5, `finance.till.view`).
 * Session history filterable by till and cashier, with each row's own
 * variance so a supervisor can spot a cashier's variance trend without
 * a separate report.
 */
#[Title('Till sessions')]
#[Layout('layouts.app')]
final class Sessions extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;
    use WithPagination;

    public ?int $tillId = null;

    public ?int $cashierId = null;

    public string $status = '';

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('finance.till.view');
    }

    public function updating(): void
    {
        $this->resetPage();
    }

    public function render(): View
    {
        $sessions = TillSession::query()
            ->with(['till', 'cashier'])
            ->when($this->tillId !== null, fn (Builder $query): Builder => $query->where('till_id', $this->tillId))
            ->when($this->cashierId !== null, fn (Builder $query): Builder => $query->where('cashier_id', $this->cashierId))
            ->when($this->status !== '', fn (Builder $query): Builder => $query->where('status', $this->status))
            ->orderByDesc('opened_at')
            ->paginate(20);

        return view('finance::till.sessions', [
            'sessions' => $sessions,
            'tills' => Till::orderBy('code')->get(),
            'cashiers' => TillSession::query()->with('cashier')->get()->pluck('cashier')->filter()->unique('id')->sortBy('name'),
        ]);
    }
}
