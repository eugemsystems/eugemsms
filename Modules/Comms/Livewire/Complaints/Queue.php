<?php

declare(strict_types=1);

namespace Modules\Comms\Livewire\Complaints;

use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Comms\Models\Complaint;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\School;
use Modules\People\Models\Staff;

/**
 * `Comms\Complaints\Queue` (Book I COM-08 §4, `complaints.manage`).
 * Open complaints soonest-deadline first, a breached one highlighted
 * (BR-COM-08-004). A complaint routed to safeguarding (BR-COM-08-006)
 * is listed by number and category only: its subject, description and
 * raiser are never selected, because that content belongs to the
 * safeguarding case and must not be readable from the ordinary queue.
 */
#[Title('Complaint queue')]
#[Layout('layouts.app')]
final class Queue extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;

    public string $statusFilter = 'open';

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('complaints.manage');
    }

    public function render(): View
    {
        $complaints = Complaint::with('category:id,name')
            ->where('school_id', $this->school->id)
            ->when($this->statusFilter === 'open', fn ($query) => $query->whereNotIn('status', ['resolved', 'closed']))
            ->when($this->statusFilter === 'overdue', fn ($query) => $query->whereNotIn('status', ['resolved', 'closed'])->where('sla_due_at', '<', now()))
            ->when(in_array($this->statusFilter, ['received', 'acknowledged', 'investigating', 'escalated', 'resolved', 'closed'], true), fn ($query) => $query->where('status', $this->statusFilter))
            ->orderBy('sla_due_at')
            ->limit(100)
            ->get(['id', 'ulid', 'complaint_number', 'category_id', 'severity', 'assigned_to_staff_id', 'sla_due_at', 'status', 'safeguarding_concern_id']);

        // The subject is selected separately, and only for complaints that are NOT routed to safeguarding.
        $subjects = Complaint::where('school_id', $this->school->id)
            ->whereIn('id', $complaints->reject(fn (Complaint $complaint): bool => $complaint->isRoutedToSafeguarding())->pluck('id'))
            ->pluck('subject', 'id');

        return view('comms::complaints.queue', [
            'complaints' => $complaints,
            'subjects' => $subjects,
            'assignees' => Staff::where('school_id', $this->school->id)->whereIn('id', $complaints->pluck('assigned_to_staff_id')->filter())->get()->keyBy('id'),
        ]);
    }
}
