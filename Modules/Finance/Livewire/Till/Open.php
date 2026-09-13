<?php

declare(strict_types=1);

namespace Modules\Finance\Livewire\Till;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Core\Domain\Exceptions\DomainException;
use Modules\Core\Domain\Support\SessionContext;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Livewire\Sessions\Concerns\InteractsWithSession;
use Modules\Core\Models\School;
use Modules\Finance\Domain\Actions\OpenTillSessionAction;
use Modules\Finance\Domain\DataObjects\OpenTillSessionData;
use Modules\Finance\Models\Till;
use Modules\Finance\Models\TillSession;

/**
 * `Finance\Till\Open` (Book B FIN-04 §3/BR-FIN-04-001/002,
 * `finance.till.operate`). A cashier may hold at most one open session
 * at a time — `OpenTillSessionAction` itself is the enforcement point.
 */
#[Title('Open till')]
#[Layout('layouts.app')]
final class Open extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;
    use InteractsWithSession;

    public ?int $tillId = null;

    /**
     * @var array<string, string>
     */
    public array $openingFloat = ['USD' => '', 'ZWG' => ''];

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->loadSessionContext($school);
        $this->authorizePermission('finance.till.operate');
    }

    public function open(): void
    {
        $this->validate([
            'tillId' => ['required', 'integer'],
        ]);

        $yearId = SessionContext::yearId();
        $termId = SessionContext::termId();

        if ($yearId === null || $termId === null) {
            $this->addError('tillId', __('No active academic year/term is set for this school.'));

            return;
        }

        $floats = [];

        foreach ($this->openingFloat as $currency => $amount) {
            if ($amount !== '' && is_numeric($amount)) {
                $floats[$currency] = (int) round((float) $amount * 100);
            }
        }

        try {
            $session = app(OpenTillSessionAction::class)->execute(new OpenTillSessionData(
                schoolId: $this->school->id,
                academicYearId: $yearId,
                termId: $termId,
                tillId: (int) $this->tillId,
                cashierId: (int) Auth::id(),
                openingFloat: $floats,
            ));
        } catch (DomainException $e) {
            $this->addError('tillId', $e->getMessage());

            return;
        }

        $this->redirectRoute('finance.receipts.capture', ['school' => $this->school, 'tillSession' => $session], navigate: true);
    }

    public function render(): View
    {
        return view('finance::till.open', [
            'tills' => Till::where('is_active', true)->orderBy('code')->get(),
            'myOpenSession' => TillSession::where('cashier_id', Auth::id())->where('status', 'open')->first(),
        ]);
    }
}
