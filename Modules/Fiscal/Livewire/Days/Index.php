<?php

declare(strict_types=1);

namespace Modules\Fiscal\Livewire\Days;

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
use Modules\Fiscal\Domain\Actions\CloseFiscalDayAction;
use Modules\Fiscal\Domain\Actions\CompileAndSubmitZReportAction;
use Modules\Fiscal\Domain\Actions\OpenFiscalDayAction;
use Modules\Fiscal\Domain\DataObjects\CloseFiscalDayData;
use Modules\Fiscal\Domain\DataObjects\OpenFiscalDayData;
use Modules\Fiscal\Models\FiscalDay;
use Modules\Fiscal\Models\FiscalDevice;

/**
 * `Fiscal\Days\Index` (Book H3 FIN-13 §5/§7, `fiscal.day.manage`).
 * Opening never waits on FDMS (`OpenFiscalDayAction`'s own docblock —
 * the same cardinal rule as receipting itself); closing genuinely
 * does, and `local_status` only ever moves to `closed` on driver
 * confirmation (BR-FIN-13-007). A `close_failed` day retries by
 * calling close again — never a separate "reopen" control, since one
 * does not exist in the domain layer (BR-FIN-13-008's "never silently
 * reopens"). Compiling the Z-report (`CompileAndSubmitZReportAction`)
 * is offered once a day is closed, folding the spec's own separate
 * screen's trigger in here rather than duplicating the day list.
 */
#[Title('Fiscal day control')]
#[Layout('layouts.app')]
final class Index extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;
    use Toasts;

    public ?int $deviceId = null;

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('fiscal.day.manage');
    }

    public function openDay(): void
    {
        $this->validate(['deviceId' => ['required', 'integer']]);

        try {
            app(OpenFiscalDayAction::class)->execute(new OpenFiscalDayData(
                deviceId: (int) $this->deviceId,
                openedByUserId: (int) Auth::id(),
            ));
        } catch (DomainException $e) {
            $this->toast($e->getMessage(), 'danger');

            return;
        }

        $this->toast(__('Fiscal day opened.'));
    }

    public function closeDay(int $fiscalDayId): void
    {
        try {
            $day = app(CloseFiscalDayAction::class)->execute(new CloseFiscalDayData(
                fiscalDayId: $fiscalDayId,
                closedByUserId: (int) Auth::id(),
            ));
        } catch (DomainException $e) {
            $this->toast($e->getMessage(), 'danger');

            return;
        }

        $this->toast($day->local_status === 'closed' ? __('Day closed.') : __('Close attempted — FDMS has not yet confirmed. Retry by closing again.'), $day->local_status === 'closed' ? 'success' : 'warning');
    }

    public function compileZReport(int $fiscalDayId): void
    {
        try {
            app(CompileAndSubmitZReportAction::class)->execute($fiscalDayId);
        } catch (DomainException $e) {
            $this->toast($e->getMessage(), 'danger');

            return;
        }

        $this->toast(__('Z-report compiled.'));
    }

    public function render(): View
    {
        return view('fiscal::days.index', [
            'days' => FiscalDay::where('school_id', $this->school->id)->orderByDesc('id')->limit(30)->get(),
            'devices' => FiscalDevice::where('school_id', $this->school->id)->where('is_active', true)->get(),
        ]);
    }
}
