<?php

declare(strict_types=1);

namespace Modules\Utilities\Livewire\Tokens;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Core\Domain\Support\SessionContext;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Livewire\Sessions\Concerns\InteractsWithSession;
use Modules\Core\Models\School;
use Modules\Finance\Models\Account;
use Modules\Utilities\Domain\Actions\CheckTokenReconciliationAction;
use Modules\Utilities\Domain\Actions\CheckUncreditedTokensAction;
use Modules\Utilities\Domain\Actions\ConfirmTokenCreditAction;
use Modules\Utilities\Domain\Actions\PurchasePrepaidTokenAction;
use Modules\Utilities\Domain\DataObjects\PurchasePrepaidTokenData;
use Modules\Utilities\Models\Meter;
use Modules\Utilities\Models\PrepaidTokenPurchase;

/**
 * `Tokens\Index` (Book H2 OPS-04 §3/§6 🇿🇼 ⭐, `utilities.token.record`).
 * The real control: a token purchased and never confirmed credited is
 * money nobody noticed went missing. The uncredited queue and the
 * monthly reconciliation check are both one button away — never a
 * silent background-only job the estates manager has to take on faith.
 */
#[Title('Prepaid tokens')]
#[Layout('layouts.app')]
final class Index extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;
    use InteractsWithSession;
    use Toasts;

    public ?int $meterId = null;

    public string $tokenNumber = '';

    public string $amountPaidMinor = '';

    public string $unitsPurchased = '';

    public string $leviesMinor = '0';

    public ?string $vendor = null;

    public ?int $prepaidAssetAccountId = null;

    public ?int $contraAccountId = null;

    /** @var array<int, PrepaidTokenPurchase> */
    public array $uncreditedMeters = [];

    public int $reconciliationFlaggedCount = 0;

    public bool $reconciliationChecked = false;

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->loadSessionContext($school);
        $this->authorizePermission('utilities.token.record');
    }

    public function purchase(): void
    {
        $this->validate([
            'meterId' => ['required', 'integer'],
            'tokenNumber' => ['required', 'string'],
            'amountPaidMinor' => ['required', 'integer', 'gt:0'],
            'unitsPurchased' => ['required', 'numeric', 'gt:0'],
            'prepaidAssetAccountId' => ['required', 'integer'],
            'contraAccountId' => ['required', 'integer'],
        ]);

        app(PurchasePrepaidTokenAction::class)->execute(new PurchasePrepaidTokenData(
            schoolId: $this->school->id,
            academicYearId: (int) SessionContext::yearId(),
            termId: (int) SessionContext::termId(),
            meterId: (int) $this->meterId,
            purchasedAt: Carbon::now(),
            tokenNumber: $this->tokenNumber,
            amountPaidMinor: (int) $this->amountPaidMinor,
            currency: $this->school->base_currency,
            unitsPurchased: (float) $this->unitsPurchased,
            prepaidAssetAccountId: (int) $this->prepaidAssetAccountId,
            contraAccountId: (int) $this->contraAccountId,
            purchasedByUserId: (int) auth()->id(),
            leviesMinor: (int) $this->leviesMinor,
            vendor: $this->vendor !== '' ? $this->vendor : null,
        ));

        $this->reset(['tokenNumber', 'amountPaidMinor', 'unitsPurchased', 'leviesMinor', 'vendor']);
        $this->leviesMinor = '0';
        $this->toast(__('Token purchase recorded — uncredited until confirmed.'));
    }

    public function confirmCredit(int $tokenPurchaseId): void
    {
        app(ConfirmTokenCreditAction::class)->execute($tokenPurchaseId, (int) auth()->id());
        $this->toast(__('Token credit confirmed.'));
    }

    public function checkUncredited(): void
    {
        $this->uncreditedMeters = app(CheckUncreditedTokensAction::class)->execute($this->school->id)->all();
    }

    public function checkReconciliation(): void
    {
        $flagged = app(CheckTokenReconciliationAction::class)->execute(
            $this->school->id,
            Carbon::now()->startOfMonth(),
            Carbon::now()->endOfMonth(),
        );

        $this->reconciliationFlaggedCount = $flagged->count();
        $this->reconciliationChecked = true;
    }

    public function render(): View
    {
        return view('utilities::tokens.index', [
            'purchases' => PrepaidTokenPurchase::with('meter')->where('school_id', $this->school->id)->orderByDesc('purchased_at')->limit(50)->get(),
            'meters' => Meter::where('school_id', $this->school->id)->where('meter_type', 'electricity_prepaid')->orderBy('meter_number')->get(),
            'accounts' => Account::where('school_id', $this->school->id)->where('is_postable', true)->orderBy('code')->get(),
        ]);
    }
}
