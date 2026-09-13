<?php

declare(strict_types=1);

namespace Modules\Finance\Livewire\Reports;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\School;
use Modules\Core\Models\Term;
use Modules\Finance\Domain\Actions\GenerateTrialBalanceAction;
use Modules\Finance\Domain\DataObjects\GenerateTrialBalanceData;

/**
 * `Finance\Reports\TrialBalance` (Book B FIN-01 §8, `finance.report.trial_balance`)
 * — always computed from source (BR-FIN-01-026), never from
 * `account_balances`. `asAt` defaults to today; a school may re-run it
 * for any prior date, since every journal carries its own `effective_at`.
 */
#[Title('Trial balance')]
#[Layout('layouts.app')]
final class TrialBalance extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;

    public string $asAt;

    public ?int $termId = null;

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('finance.report.trial_balance');
        $this->asAt = now()->toDateString();
    }

    public function render(): View
    {
        $trialBalance = app(GenerateTrialBalanceAction::class)->execute(new GenerateTrialBalanceData(
            schoolId: $this->school->id,
            asAt: Carbon::parse($this->asAt),
            termId: $this->termId,
        ));

        return view('finance::reports.trial-balance', [
            'trialBalance' => $trialBalance,
            'terms' => Term::query()->where('school_id', $this->school->id)->orderByDesc('starts_on')->get(),
        ]);
    }
}
