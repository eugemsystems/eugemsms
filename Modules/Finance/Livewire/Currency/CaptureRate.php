<?php

declare(strict_types=1);

namespace Modules\Finance\Livewire\Currency;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Core\Domain\Exceptions\DomainException;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\School;
use Modules\Finance\Domain\Actions\CaptureExchangeRateAction;
use Modules\Finance\Domain\DataObjects\CaptureExchangeRateData;
use Modules\Finance\Models\ExchangeRateSource;

/**
 * `Finance\Currency\CaptureRate` (Book B FIN-06 §6, `finance.rate.capture`)
 * — a new exchange rate. Never edits a rate in place (BR-FIN-06-004):
 * this always inserts a new row, landing `active` immediately or
 * `pending` depending on the chosen source's own `requires_approval`
 * flag (BR-FIN-06-005) — the source, not this screen, decides.
 */
#[Title('Capture exchange rate')]
#[Layout('layouts.app')]
final class CaptureRate extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;

    public ?int $sourceId = null;

    public string $fromCurrency = 'USD';

    public string $toCurrency = 'ZWG';

    public string $rate = '';

    public string $effectiveFrom;

    public ?string $notes = null;

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('finance.rate.capture');
        $this->effectiveFrom = now()->format('Y-m-d\TH:i');
    }

    public function save(): void
    {
        $this->validate([
            'sourceId' => ['required', 'integer'],
            'fromCurrency' => ['required', 'size:3', 'different:toCurrency'],
            'toCurrency' => ['required', 'size:3'],
            'rate' => ['required', 'regex:/^\d+(\.\d{1,10})?$/'],
            'effectiveFrom' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:255'],
        ]);

        try {
            app(CaptureExchangeRateAction::class)->execute(new CaptureExchangeRateData(
                schoolId: $this->school->id,
                sourceId: (int) $this->sourceId,
                fromCurrency: $this->fromCurrency,
                toCurrency: $this->toCurrency,
                rate: $this->rate,
                effectiveFrom: Carbon::parse($this->effectiveFrom),
                capturedByUserId: (int) Auth::id(),
                notes: $this->notes,
            ));
        } catch (DomainException $e) {
            $this->addError('rate', $e->getMessage());

            return;
        }

        $this->redirectRoute('finance.currency.rates', ['school' => $this->school], navigate: true);
    }

    public function render(): View
    {
        return view('finance::currency.capture-rate', [
            'sources' => ExchangeRateSource::query()
                ->where('is_active', true)
                ->where(fn ($q) => $q->whereNull('school_id')->orWhere('school_id', $this->school->id))
                ->orderBy('priority')
                ->get(),
        ]);
    }
}
