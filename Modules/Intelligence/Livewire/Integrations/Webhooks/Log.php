<?php

declare(strict_types=1);

namespace Modules\Intelligence\Livewire\Integrations\Webhooks;

use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\School;
use Modules\Intelligence\Models\WebhookDelivery;

/**
 * `Intelligence\Integrations\Webhooks\Log` (Book J INT-04 §5,
 * `integration.view`). The append-only delivery record. Payloads are
 * deliberately not shown — they carry the event's data, which
 * `integration.view` alone does not entitle anyone to read.
 */
#[Title('Webhook delivery log')]
#[Layout('layouts.app')]
final class Log extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;

    /** @var array<int, string> */
    public const array STATUSES = ['pending', 'delivered', 'failed', 'abandoned'];

    public string $status = '';

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('integration.view');
    }

    public function render(): View
    {
        return view('intelligence::integrations.webhook-log', [
            'deliveries' => WebhookDelivery::where('school_id', $this->school->id)
                ->when(in_array($this->status, self::STATUSES, true), fn ($q) => $q->where('status', $this->status))
                ->orderByDesc('id')->limit(200)->get(['id', 'subscription_id', 'event_name', 'status', 'attempt_count', 'response_status', 'last_attempted_at']),
            'statuses' => self::STATUSES,
        ]);
    }
}
