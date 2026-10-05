<?php

declare(strict_types=1);

namespace Modules\Comms\Livewire\Messaging\Gateways;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Comms\Domain\Actions\CheckGatewayHealthAction;
use Modules\Comms\Domain\Actions\RegisterMessageGatewayAction;
use Modules\Comms\Domain\Actions\SetMessageGatewayActiveAction;
use Modules\Comms\Domain\DataObjects\RegisterMessageGatewayData;
use Modules\Comms\Models\MessageGateway;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\School;

/**
 * `Comms\Gateways\Index` (Book I COM-01 §6, `comms.gateway.manage` ⚠⚠).
 * Credentials are write-only here (BR-COM-01-001): the form accepts a
 * JSON blob, the list never reads `credentials`/`webhook_secret` back
 * into any view — only name, channel, driver, health and priority.
 * The spec's "test send" is deliberately not built: it needs a
 * recipient-resolving dispatch path `CORE-09` owns, and a gateway
 * health check already exercises the driver end to end.
 */
#[Title('Messaging gateways')]
#[Layout('layouts.app')]
final class Index extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;
    use Toasts;

    public string $channel = 'sms';

    public string $driver = '';

    public string $name = '';

    public string $credentials = '';

    public string $webhookSecret = '';

    public int $priority = 0;

    public bool $isDefault = false;

    public bool $isSandbox = true;

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('comms.gateway.manage');
    }

    public function register(): void
    {
        $this->authorizePermission('comms.gateway.manage');

        $this->validate([
            'channel' => ['required', 'in:sms,whatsapp,email,push'],
            'driver' => ['required', 'string', 'max:30'],
            'name' => ['required', 'string', 'max:120'],
            'credentials' => ['required', 'json'],
            'webhookSecret' => ['nullable', 'string', 'max:255'],
            'priority' => ['required', 'integer', 'min:0', 'max:99'],
        ]);

        app(RegisterMessageGatewayAction::class)->execute(new RegisterMessageGatewayData(
            schoolId: $this->school->id,
            channel: $this->channel,
            driver: $this->driver,
            name: $this->name,
            credentials: $this->credentials,
            createdByUserId: (int) auth()->id(),
            webhookSecret: $this->webhookSecret !== '' ? $this->webhookSecret : null,
            isDefault: $this->isDefault,
            isSandbox: $this->isSandbox,
            priority: $this->priority,
        ));

        $this->reset(['driver', 'name', 'credentials', 'webhookSecret', 'priority', 'isDefault']);
        $this->toast(__('Gateway registered.'));
    }

    public function checkHealth(): void
    {
        $this->authorizePermission('comms.gateway.manage');

        $checked = app(CheckGatewayHealthAction::class)->execute($this->school->id);

        $this->toast(__(':count gateway(s) checked.', ['count' => $checked->count()]));
    }

    public function toggleActive(int $gatewayId): void
    {
        $this->authorizePermission('comms.gateway.manage');

        $gateway = MessageGateway::where('school_id', $this->school->id)->findOrFail($gatewayId);
        app(SetMessageGatewayActiveAction::class)->execute($gateway->id, ! $gateway->is_active);

        $this->toast($gateway->is_active ? __('Gateway deactivated.') : __('Gateway activated.'));
    }

    public function render(): View
    {
        return view('comms::gateways.index', [
            'gateways' => MessageGateway::where('school_id', $this->school->id)
                ->orderBy('channel')->orderBy('priority')
                ->get(['id', 'channel', 'driver', 'name', 'priority', 'is_default', 'is_sandbox', 'is_active', 'health_status', 'last_health_check_at']),
        ]);
    }
}
