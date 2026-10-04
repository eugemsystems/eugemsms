<?php

declare(strict_types=1);

namespace Modules\Finance\Livewire\Gateways;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Core\Domain\Exceptions\DomainException;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\School;
use Modules\Finance\Domain\Actions\CheckPaymentGatewayHealthAction;
use Modules\Finance\Domain\Actions\RegisterPaymentGatewayAction;
use Modules\Finance\Domain\Actions\UpdatePaymentGatewayAction;
use Modules\Finance\Domain\DataObjects\RegisterPaymentGatewayData;
use Modules\Finance\Domain\DataObjects\UpdatePaymentGatewayData;
use Modules\Finance\Domain\Support\FakePaymentGatewayDriver;
use Modules\Finance\Models\Account;
use Modules\Finance\Models\PaymentGateway;

/**
 * `Finance\Gateways\Index` (Book B FIN-05 §3/§8 ⭐, `finance.gateway.manage`).
 * `PaymentGatewayDriverRegistry` registers only `FakePaymentGatewayDriver`
 * in this build (see `.ai/rules/finance.md`'s own "FIN-05 is a driver-
 * abstraction-only gateway/reconciliation engine" note) — the driver
 * picker offers exactly that one option, clearly labelled, rather than
 * listing ContiPay/Pesepay/Paynow/SmilePay with nothing behind them.
 * `RegisterPaymentGatewayAction` only ever created a gateway; editing
 * one had no Action at all until `UpdatePaymentGatewayAction` (added
 * alongside this screen).
 */
#[Title('Payment gateways')]
#[Layout('layouts.app')]
final class Index extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;
    use Toasts;

    public bool $showFormModal = false;

    public ?int $editingGatewayId = null;

    public string $driver = 'fake';

    public string $name = '';

    public string $credentials = '';

    /** @var array<int, string> */
    public array $supportedMethods = [];

    /** @var array<int, string> */
    public array $supportedCurrencies = [];

    public ?int $settlementAccountId = null;

    public ?int $feeAccountId = null;

    public string $feeType = 'percentage';

    public string $feeValue = '';

    public string $feeCap = '';

    public bool $isDefault = false;

    public bool $isSandbox = true;

    public bool $isActive = false;

    public int $priority = 0;

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('finance.gateway.manage');
    }

    public function openCreateModal(): void
    {
        $this->reset([
            'editingGatewayId', 'name', 'credentials', 'supportedMethods', 'supportedCurrencies',
            'settlementAccountId', 'feeAccountId', 'feeType', 'feeValue', 'feeCap',
            'isDefault', 'isSandbox', 'isActive', 'priority',
        ]);
        $this->driver = 'fake';
        $this->feeType = 'percentage';
        $this->isSandbox = true;
        $this->resetErrorBag();
        $this->showFormModal = true;
    }

    public function openEditModal(int $gatewayId): void
    {
        $gateway = PaymentGateway::where('school_id', $this->school->id)->findOrFail($gatewayId);

        $this->editingGatewayId = $gateway->id;
        $this->driver = $gateway->driver;
        $this->name = $gateway->name;
        $this->credentials = '';
        $this->supportedMethods = $gateway->supported_methods;
        $this->supportedCurrencies = $gateway->supported_currencies;
        $this->settlementAccountId = $gateway->settlement_account_id;
        $this->feeAccountId = $gateway->fee_account_id;
        $this->feeType = $gateway->fee_model['type'] ?? 'percentage';
        $this->feeValue = isset($gateway->fee_model['value']) ? (string) $gateway->fee_model['value'] : '';
        $this->feeCap = isset($gateway->fee_model['cap']) ? (string) ($gateway->fee_model['cap'] / 100) : '';
        $this->isDefault = $gateway->is_default;
        $this->isSandbox = $gateway->is_sandbox;
        $this->isActive = $gateway->is_active;
        $this->priority = $gateway->priority;
        $this->resetErrorBag();
        $this->showFormModal = true;
    }

    public function save(): void
    {
        $this->validate([
            'name' => ['required', 'string', 'max:120'],
            'credentials' => [$this->editingGatewayId === null ? 'required' : 'nullable', 'string'],
            'supportedMethods' => ['required', 'array', 'min:1'],
            'supportedCurrencies' => ['required', 'array', 'min:1'],
            'settlementAccountId' => ['required', 'integer'],
            'feeAccountId' => ['required', 'integer'],
            'priority' => ['integer'],
        ]);

        $feeModel = $this->feeValue !== '' ? [
            'type' => $this->feeType,
            'value' => (float) $this->feeValue,
            ...($this->feeCap !== '' ? ['cap' => (int) round((float) $this->feeCap * 100)] : []),
        ] : null;

        try {
            if ($this->editingGatewayId === null) {
                app(RegisterPaymentGatewayAction::class)->execute(new RegisterPaymentGatewayData(
                    schoolId: $this->school->id,
                    driver: $this->driver,
                    name: $this->name,
                    credentials: $this->credentials,
                    supportedMethods: $this->supportedMethods,
                    supportedCurrencies: $this->supportedCurrencies,
                    settlementAccountId: (int) $this->settlementAccountId,
                    feeAccountId: (int) $this->feeAccountId,
                    feeModel: $feeModel,
                    isDefault: $this->isDefault,
                    isSandbox: $this->isSandbox,
                    isActive: $this->isActive,
                ));
            } else {
                app(UpdatePaymentGatewayAction::class)->execute(new UpdatePaymentGatewayData(
                    gatewayId: $this->editingGatewayId,
                    name: $this->name,
                    supportedMethods: $this->supportedMethods,
                    supportedCurrencies: $this->supportedCurrencies,
                    settlementAccountId: (int) $this->settlementAccountId,
                    feeAccountId: (int) $this->feeAccountId,
                    feeModel: $feeModel,
                    isDefault: $this->isDefault,
                    isSandbox: $this->isSandbox,
                    isActive: $this->isActive,
                    priority: $this->priority,
                    credentials: $this->credentials !== '' ? $this->credentials : null,
                ));
            }
        } catch (DomainException $e) {
            $this->addError('name', $e->getMessage());

            return;
        }

        $this->showFormModal = false;
        $this->toast(__('Gateway saved.'));
    }

    public function testConnection(int $gatewayId): void
    {
        $gateway = PaymentGateway::where('school_id', $this->school->id)->findOrFail($gatewayId);
        $result = app(CheckPaymentGatewayHealthAction::class)->execute($gateway->id);

        $this->toast(__('Health check for :name: :status', ['name' => $gateway->name, 'status' => $result->health_status]));
    }

    public function render(): View
    {
        return view('finance::gateways.index', [
            'gateways' => PaymentGateway::where('school_id', $this->school->id)->orderBy('priority')->orderBy('name')->get(),
            'accounts' => Account::where('school_id', $this->school->id)->where('is_active', true)->orderBy('code')->get(),
            'methodOptions' => (new FakePaymentGatewayDriver)->supportedMethods(),
            'currencyOptions' => (new FakePaymentGatewayDriver)->supportedCurrencies(),
        ]);
    }
}
