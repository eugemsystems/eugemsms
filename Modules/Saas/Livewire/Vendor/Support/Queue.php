<?php

declare(strict_types=1);

namespace Modules\Saas\Livewire\Vendor\Support;

use App\Concerns\Toasts;
use App\Models\User;
use Illuminate\Contracts\View\View;
use InvalidArgumentException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Core\Domain\Scopes\SchoolScope;
use Modules\Core\Domain\Support\Auth\UserType;
use Modules\Core\Models\School;
use Modules\Core\Models\Tenant;
use Modules\Saas\Domain\Actions\AssignSupportTicketAction;
use Modules\Saas\Domain\Actions\ChangeSupportTicketStatusAction;
use Modules\Saas\Domain\Actions\RecordVendorConsoleActionAction;
use Modules\Saas\Livewire\Concerns\AuthorizesVendorConsole;
use Modules\Saas\Models\SupportTicket;

/**
 * `Success\Support\Queue` (Book J SAA-03 §5, vendor console). The vendor's
 * ticket queue, SLA-sorted, across every tenant. These are tickets from
 * school users to the VENDOR — they live in `support_tickets` and never in
 * a school's own COM-08 complaint queue (BR-SAA-03-003). Reads lift the
 * school scope deliberately, only after the console gate.
 */
#[Title('Support queue')]
#[Layout('saas::layouts.vendor')]
final class Queue extends Component
{
    use AuthorizesVendorConsole;
    use Toasts;

    public string $statusFilter = 'active';

    public function mount(): void
    {
        $this->authorizeVendor();
    }

    public function assign(int $ticketId, int $vendorUserId): void
    {
        $operator = $this->authorizeVendor();

        $ticket = SupportTicket::query()->withoutGlobalScope(SchoolScope::class)->findOrFail($ticketId);

        try {
            app(AssignSupportTicketAction::class)->execute($ticket->id, $vendorUserId);
        } catch (InvalidArgumentException $exception) {
            $this->toast($exception->getMessage(), 'danger');

            return;
        }

        app(RecordVendorConsoleActionAction::class)->execute($operator, 'ticket.assigned', "Ticket {$ticket->id} assigned", $ticket->tenant_id, ['ticket_id' => $ticket->id, 'assignee' => $vendorUserId]);

        $this->toast(__('Assigned.'));
    }

    public function changeStatus(int $ticketId, string $status): void
    {
        $operator = $this->authorizeVendor();

        $ticket = SupportTicket::query()->withoutGlobalScope(SchoolScope::class)->findOrFail($ticketId);

        try {
            app(ChangeSupportTicketStatusAction::class)->execute($ticket->id, $status);
        } catch (InvalidArgumentException $exception) {
            $this->toast($exception->getMessage(), 'danger');

            return;
        }

        app(RecordVendorConsoleActionAction::class)->execute($operator, 'ticket.status_changed', "Ticket {$ticket->id}: {$ticket->status} → {$status}", $ticket->tenant_id, ['ticket_id' => $ticket->id]);

        $this->toast(__('Ticket updated.'));
    }

    public function render(): View
    {
        $this->authorizeVendor();

        $tickets = SupportTicket::query()->withoutGlobalScope(SchoolScope::class)
            ->when($this->statusFilter === 'active', fn ($q) => $q->whereNotIn('status', ['resolved', 'closed']))
            ->when(in_array($this->statusFilter, array_keys(ChangeSupportTicketStatusAction::TRANSITIONS), true), fn ($q) => $q->where('status', $this->statusFilter))
            ->orderBy('sla_due_at')->limit(200)->get();

        return view('saas::vendor.support-queue', [
            'tickets' => $tickets,
            'transitions' => ChangeSupportTicketStatusAction::TRANSITIONS,
            'tenantNames' => Tenant::query()->whereIn('id', $tickets->pluck('tenant_id'))->pluck('name', 'id'),
            'schoolNames' => School::query()->whereIn('id', $tickets->pluck('school_id')->filter())->pluck('name', 'id'),
            'authors' => User::query()->whereIn('id', $tickets->pluck('raised_by_user_id'))->pluck('name', 'id'),
            'staff' => User::query()->where('user_type', UserType::Vendor)->orderBy('name')->get(['id', 'name']),
        ]);
    }
}
