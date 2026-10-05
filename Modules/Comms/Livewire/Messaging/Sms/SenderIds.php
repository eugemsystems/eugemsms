<?php

declare(strict_types=1);

namespace Modules\Comms\Livewire\Messaging\Sms;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Comms\Domain\Actions\RegisterSenderIdAction;
use Modules\Comms\Domain\Actions\UpdateSenderIdStatusAction;
use Modules\Comms\Domain\DataObjects\RegisterSenderIdData;
use Modules\Comms\Models\MessageGateway;
use Modules\Comms\Models\SenderId;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\School;

/**
 * `Comms\Sms\SenderIds` (Book I COM-01 §6, `comms.sender_id.manage`).
 * Per-network status (BR-COM-01-008): a pending or rejected sender ID
 * is never used by the SMS driver — it falls back to a generic sender
 * ID or another gateway, which is why a rejection reason is kept here.
 */
#[Title('SMS sender IDs')]
#[Layout('layouts.app')]
final class SenderIds extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;
    use Toasts;

    public ?int $gatewayId = null;

    public string $senderId = '';

    public string $network = '';

    public string $registrationReference = '';

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('comms.sender_id.manage');
    }

    public function register(): void
    {
        $this->authorizePermission('comms.sender_id.manage');

        $this->validate([
            'gatewayId' => ['required', 'integer'],
            'senderId' => ['required', 'string', 'max:11', 'regex:/^[A-Za-z0-9 ]+$/'],
            'network' => ['nullable', 'string', 'max:30'],
            'registrationReference' => ['nullable', 'string', 'max:100'],
        ], [
            'senderId.regex' => __('Sender IDs allow letters, digits and spaces only (max 11 characters).'),
        ]);

        $gateway = MessageGateway::where('school_id', $this->school->id)->where('channel', 'sms')->findOrFail($this->gatewayId);

        app(RegisterSenderIdAction::class)->execute(new RegisterSenderIdData(
            schoolId: $this->school->id,
            gatewayId: $gateway->id,
            senderId: $this->senderId,
            network: $this->network !== '' ? $this->network : null,
            registrationReference: $this->registrationReference !== '' ? $this->registrationReference : null,
        ));

        $this->reset(['senderId', 'network', 'registrationReference']);
        $this->toast(__('Sender ID registered as pending.'));
    }

    public function recordStatus(int $id, string $status, ?string $reason = null): void
    {
        $this->authorizePermission('comms.sender_id.manage');

        $sender = SenderId::where('school_id', $this->school->id)->findOrFail($id);
        app(UpdateSenderIdStatusAction::class)->execute($sender->id, $status, $reason);

        $this->toast(__('Sender ID status updated.'));
    }

    public function render(): View
    {
        return view('comms::sms.sender-ids', [
            'smsGateways' => MessageGateway::where('school_id', $this->school->id)->where('channel', 'sms')->orderBy('name')->get(['id', 'name']),
            'senderIds' => SenderId::where('school_id', $this->school->id)->orderBy('network')->orderBy('sender_id')->get(),
        ]);
    }
}
