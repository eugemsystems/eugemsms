<?php

declare(strict_types=1);

namespace Modules\Comms\Livewire\Complaints;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Comms\Domain\Actions\AddComplaintUpdateAction;
use Modules\Comms\Domain\Actions\AssignComplaintAction;
use Modules\Comms\Domain\Actions\ChangeComplaintStatusAction;
use Modules\Comms\Domain\Actions\GetComplaintThreadForRaiserAction;
use Modules\Comms\Domain\Actions\ResolveComplaintAction;
use Modules\Comms\Models\Complaint;
use Modules\Comms\Models\ComplaintUpdate;
use Modules\Core\Domain\Exceptions\InvalidStateTransitionException;
use Modules\Core\Domain\Support\Auth\PermissionScope;
use Modules\Core\Domain\Support\Auth\PermissionScopeResolver;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\School;
use Modules\People\Models\Staff;
use Modules\People\Models\Student;

/**
 * `Comms\Complaints\Show` (Book I COM-08 §4 — "assignee"). Open to
 * anyone with `complaints.manage` *or* the complaint's own assignee;
 * assigning is `complaints.manage` only. The raiser-visible thread and
 * the internal notes are rendered as two separate lists, and the
 * raiser-visible one is fetched through
 * `GetComplaintThreadForRaiserAction` — the same call the raiser's own
 * view uses — so what the assignee sees there is exactly what the
 * raiser sees (BR-COM-08-005).
 *
 * A complaint routed to safeguarding (BR-COM-08-006) renders as a stub:
 * its subject, description, raiser, related learner and thread are never
 * selected, and no action is offered. The complaint is held only by its
 * id here, and the page is looked up by ulid after the school context is
 * set.
 */
#[Title('Complaint')]
#[Layout('layouts.app')]
final class Show extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;
    use Toasts;

    public int $complaintId = 0;

    public string $note = '';

    public bool $visibleToRaiser = false;

    public ?int $assigneeId = null;

    public string $resolution = '';

    public function mount(School $school, string $complaint): void
    {
        $this->loadSchool($school);

        $found = Complaint::where('school_id', $school->id)->where('ulid', $complaint)->firstOrFail(['id', 'assigned_to_staff_id']);
        abort_unless($this->mayWork($found), 403);

        $this->complaintId = $found->id;
    }

    public function post(): void
    {
        $complaint = $this->workableComplaint();

        $this->validate(['note' => ['required', 'string', 'max:5000']]);

        $visible = $this->visibleToRaiser;

        app(AddComplaintUpdateAction::class)->execute($complaint->id, 'comment', (int) auth()->id(), $this->note, $visible);

        $this->reset(['note', 'visibleToRaiser']);
        $this->toast($visible ? __('Reply posted to the raiser.') : __('Internal note saved.'));
    }

    public function assign(): void
    {
        $this->authorizePermission('complaints.manage');
        $complaint = $this->workableComplaint();

        $this->validate(['assigneeId' => ['required', 'integer']]);

        $staff = Staff::where('school_id', $this->school->id)->findOrFail($this->assigneeId);

        try {
            app(AssignComplaintAction::class)->execute($complaint->id, $staff->id, (int) auth()->id());
        } catch (InvalidStateTransitionException $exception) {
            $this->toast($exception->getMessage(), 'danger');

            return;
        }

        $this->toast(__('Assigned to :name.', ['name' => $staff->fullName()]));
    }

    public function setStatus(string $status): void
    {
        $complaint = $this->workableComplaint();

        try {
            app(ChangeComplaintStatusAction::class)->execute($complaint->id, $status, (int) auth()->id());
        } catch (InvalidStateTransitionException $exception) {
            $this->toast($exception->getMessage(), 'danger');

            return;
        }

        $this->toast(__('Status updated.'));
    }

    public function resolve(): void
    {
        $complaint = $this->workableComplaint();

        $this->validate(['resolution' => ['required', 'string', 'max:5000']]);

        if (in_array($complaint->status, ['resolved', 'closed'], true)) {
            $this->toast(__('This complaint is already finished.'), 'danger');

            return;
        }

        app(ResolveComplaintAction::class)->execute($complaint->id, $this->resolution);

        $this->reset('resolution');
        $this->toast(__('Complaint resolved.'));
    }

    public function render(): View
    {
        $stub = Complaint::with('category:id,name')->where('school_id', $this->school->id)
            ->findOrFail($this->complaintId, ['id', 'ulid', 'complaint_number', 'category_id', 'status', 'safeguarding_concern_id']);

        if ($stub->isRoutedToSafeguarding()) {
            return view('comms::complaints.show', ['routed' => true, 'complaint' => $stub, 'canAssign' => false]);
        }

        $complaint = Complaint::with('category:id,name')->where('school_id', $this->school->id)->findOrFail($this->complaintId);

        return view('comms::complaints.show', [
            'routed' => false,
            'complaint' => $complaint,
            'student' => $complaint->related_student_id !== null ? Student::where('school_id', $this->school->id)->find($complaint->related_student_id) : null,
            'assignee' => $complaint->assigned_to_staff_id !== null ? Staff::where('school_id', $this->school->id)->find($complaint->assigned_to_staff_id) : null,
            'raiserThread' => app(GetComplaintThreadForRaiserAction::class)->execute($complaint->id),
            'internalNotes' => ComplaintUpdate::where('complaint_id', $complaint->id)->where('visible_to_raiser', false)->orderBy('posted_at')->get(),
            'staff' => Staff::where('school_id', $this->school->id)->orderBy('last_name')->limit(300)->get(),
            'canAssign' => app(PermissionScopeResolver::class)->has(auth()->user(), 'complaints.manage', PermissionScope::Own),
            'finished' => in_array($complaint->status, ['resolved', 'closed'], true),
        ]);
    }

    private function mayWork(Complaint $complaint): bool
    {
        if (app(PermissionScopeResolver::class)->has(auth()->user(), 'complaints.manage', PermissionScope::Own)) {
            return true;
        }

        return $complaint->assigned_to_staff_id !== null
            && Staff::where('school_id', $this->school->id)->where('id', $complaint->assigned_to_staff_id)->where('user_id', auth()->id())->exists();
    }

    /**
     * Re-checks access on every action (the assignee may have changed
     * since mount) and refuses a complaint routed to safeguarding.
     */
    private function workableComplaint(): Complaint
    {
        $complaint = Complaint::where('school_id', $this->school->id)->findOrFail($this->complaintId);

        abort_unless($this->mayWork($complaint), 403);
        abort_if($complaint->isRoutedToSafeguarding(), 403);

        return $complaint;
    }
}
