<?php

declare(strict_types=1);

namespace Modules\Saas\Livewire\Tenant\Support;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use InvalidArgumentException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\School;
use Modules\Saas\Domain\Actions\GetMySupportTicketsAction;
use Modules\Saas\Domain\Actions\RaiseSupportTicketAction;
use Modules\Saas\Domain\DataObjects\RaiseSupportTicketData;

/**
 * `Success\Support\Raise` (Book J SAA-03 §5, school admin). A school user
 * raises a ticket TO THE VENDOR about the software — it lands in the
 * vendor's queue and never in this school's own COM-08 complaint queue
 * (BR-SAA-03-003, AC-SAA-03-001). Tenant, school and author are derived on
 * the server, never taken from the form. The user sees only their own
 * tickets, with status and SLA.
 */
#[Title('Contact support')]
#[Layout('layouts.app')]
final class Raise extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;
    use Toasts;

    public string $category = 'how_to';

    public string $priority = 'normal';

    public string $subject = '';

    public string $description = '';

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('support.ticket.raise');

        abort_unless($this->tenantId() !== null, 403);
    }

    public function raise(): void
    {
        $this->authorizePermission('support.ticket.raise');
        $this->resetErrorBag();

        $tenantId = $this->tenantId();
        abort_if($tenantId === null, 403);

        $this->validate([
            'category' => ['required', 'in:bug,how_to,billing,feature_request'],
            'priority' => ['required', 'in:low,normal,high,urgent'],
            'subject' => ['required', 'string', 'max:200'],
            'description' => ['required', 'string', 'max:5000'],
        ]);

        try {
            app(RaiseSupportTicketAction::class)->execute(new RaiseSupportTicketData(
                tenantId: $tenantId, schoolId: $this->school->id, raisedByUserId: (int) auth()->id(),
                subject: $this->subject, description: $this->description, category: $this->category, priority: $this->priority,
            ));
        } catch (InvalidArgumentException $exception) {
            $this->addError('subject', $exception->getMessage());

            return;
        }

        $this->reset('subject', 'description');
        $this->toast(__('Ticket sent to support.'));
    }

    private function tenantId(): ?int
    {
        $userTenantId = auth()->user()?->tenant_id;

        return $userTenantId !== null && (int) $userTenantId === (int) $this->school->tenant_id ? (int) $userTenantId : null;
    }

    public function render(): View
    {
        return view('saas::tenant.support-raise', [
            'tickets' => app(GetMySupportTicketsAction::class)->execute((int) auth()->id()),
        ]);
    }
}
