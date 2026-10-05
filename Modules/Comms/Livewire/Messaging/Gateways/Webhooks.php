<?php

declare(strict_types=1);

namespace Modules\Comms\Livewire\Messaging\Gateways;

use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Comms\Models\MessagingGatewayWebhook;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\School;

/**
 * `Comms\Gateways\Webhooks` (Book I COM-01 §6, `comms.gateway.view`).
 * Read-only: `messaging_gateway_webhooks` is append-only and its raw
 * headers/payload can carry recipient numbers, so only receipt
 * metadata (driver, event type, signature validity, processing
 * outcome) is selected — never `raw_headers`/`raw_payload`.
 */
#[Title('Webhook log')]
#[Layout('layouts.app')]
final class Webhooks extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;

    public string $statusFilter = '';

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('comms.gateway.view');
    }

    public function render(): View
    {
        return view('comms::gateways.webhooks', [
            'webhooks' => MessagingGatewayWebhook::where('school_id', $this->school->id)
                ->when($this->statusFilter !== '', fn ($query) => $query->where('processing_status', $this->statusFilter))
                ->orderByDesc('received_at')
                ->limit(100)
                ->get(['id', 'driver', 'event_type', 'signature_valid', 'processing_status', 'processing_error', 'received_at', 'processed_at']),
        ]);
    }
}
