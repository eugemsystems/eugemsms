<?php

declare(strict_types=1);

namespace Modules\Finance\Livewire\Currency;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Core\Domain\Exceptions\DomainException;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\School;
use Modules\Finance\Domain\Actions\RegisterSchoolCurrencyAction;
use Modules\Finance\Domain\DataObjects\RegisterSchoolCurrencyData;
use Modules\Finance\Models\Currency as CurrencyCatalogue;
use Modules\Finance\Models\SchoolCurrency;

/**
 * `Finance\Currency\Index` (Book B FIN-06 §6, `finance.currency.manage`)
 * — which currencies a school transacts in, and which one is base.
 * `RegisterSchoolCurrencyAction` is an upsert keyed on (school, currency),
 * so "edit" is just re-submitting the form for a currency already
 * registered — there is no separate update action to call.
 */
#[Title('Currencies')]
#[Layout('layouts.app')]
final class Index extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;
    use Toasts;

    public bool $showEditModal = false;

    public string $currency = 'USD';

    public bool $isBase = false;

    public bool $isAcceptedForPayment = true;

    public int $roundingIncrementMinor = 1;

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('finance.currency.manage');
    }

    public function openEditModal(?string $currencyCode = null): void
    {
        $this->resetErrorBag();

        $existing = $currencyCode !== null
            ? SchoolCurrency::where('school_id', $this->school->id)->where('currency', $currencyCode)->first()
            : null;

        if ($existing === null) {
            $this->currency = 'USD';
            $this->isBase = false;
            $this->isAcceptedForPayment = true;
            $this->roundingIncrementMinor = 1;
        } else {
            $this->currency = $existing->currency;
            $this->isBase = $existing->is_base;
            $this->isAcceptedForPayment = $existing->is_accepted_for_payment;
            $this->roundingIncrementMinor = $existing->rounding_increment_minor;
        }

        $this->showEditModal = true;
    }

    public function save(): void
    {
        $this->validate([
            'currency' => ['required', 'size:3'],
            'roundingIncrementMinor' => ['required', 'integer', 'min:1'],
        ]);

        try {
            app(RegisterSchoolCurrencyAction::class)->execute(new RegisterSchoolCurrencyData(
                schoolId: $this->school->id,
                currency: $this->currency,
                isBase: $this->isBase,
                isAcceptedForPayment: $this->isAcceptedForPayment,
                roundingIncrementMinor: $this->roundingIncrementMinor,
            ));
        } catch (DomainException $e) {
            $this->addError('currency', $e->getMessage());

            return;
        }

        $this->showEditModal = false;
        $this->toast(__('Currency saved.'));
    }

    public function render(): View
    {
        return view('finance::currency.index', [
            'schoolCurrencies' => SchoolCurrency::where('school_id', $this->school->id)->orderByDesc('is_base')->orderBy('currency')->get(),
            'catalogue' => CurrencyCatalogue::orderBy('sort_order')->get()->keyBy('code'),
        ]);
    }
}
