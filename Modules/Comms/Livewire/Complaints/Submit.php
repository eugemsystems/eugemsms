<?php

declare(strict_types=1);

namespace Modules\Comms\Livewire\Complaints;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Comms\Domain\Actions\GetComplaintThreadForRaiserAction;
use Modules\Comms\Domain\Actions\RaiseComplaintAction;
use Modules\Comms\Domain\DataObjects\RaiseComplaintData;
use Modules\Comms\Models\Complaint;
use Modules\Comms\Models\ComplaintCategory;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\School;
use Modules\People\Models\Guardian;
use Modules\People\Models\Staff;
use Modules\People\Models\Student;

/**
 * `Comms\Complaints\Submit` (Book I COM-08 §4 — any authenticated user
 * of the school, so no permission beyond school membership). The
 * raiser's identity is derived server-side from the signed-in user (a
 * staff or guardian record), never typed in; "submit anonymously"
 * stores no identity and passes no reporter. The deadline is shown at
 * once (AC-COM-08-002). The raiser's own list reads the thread only
 * through `GetComplaintThreadForRaiserAction`, so an internal note
 * never reaches them (AC-COM-08-003), and a complaint routed to
 * safeguarding shows only that it was referred — never its content.
 */
#[Title('Raise a complaint')]
#[Layout('layouts.app')]
final class Submit extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;
    use Toasts;

    public ?int $categoryId = null;

    public string $subject = '';

    public string $description = '';

    public string $severity = 'medium';

    public string $admissionNumber = '';

    public bool $suspectedSafeguardingConcern = false;

    public bool $anonymous = false;

    public ?string $submittedNumber = null;

    public ?string $submittedDue = null;

    public function mount(School $school): void
    {
        $this->loadSchool($school);
    }

    public function submit(): void
    {
        $this->validate([
            'categoryId' => ['required', 'integer'],
            'subject' => ['required', 'string', 'max:200'],
            'description' => ['required', 'string', 'max:10000'],
            'severity' => ['required', 'in:low,medium,high'],
        ]);

        $category = ComplaintCategory::where('school_id', $this->school->id)->findOrFail($this->categoryId);

        $student = null;

        if ($this->admissionNumber !== '') {
            $student = Student::where('school_id', $this->school->id)->where('admission_number', $this->admissionNumber)->first();

            if ($student === null) {
                $this->addError('admissionNumber', __('No learner has that admission number.'));

                return;
            }
        }

        [$type, $raiserId] = $this->anonymous ? ['anonymous', null] : $this->resolveRaiser();

        $complaint = app(RaiseComplaintAction::class)->execute(new RaiseComplaintData(
            schoolId: $this->school->id,
            categoryId: $category->id,
            raisedByType: $type,
            subject: $this->subject,
            description: $this->description,
            raisedById: $raiserId,
            relatedStudentId: $student?->id,
            severity: $this->severity,
            suspectedSafeguardingConcern: $this->suspectedSafeguardingConcern,
            reporterUserId: $this->anonymous ? null : (int) auth()->id(),
        ));

        $this->submittedNumber = $complaint->complaint_number;
        $this->submittedDue = $complaint->isRoutedToSafeguarding() ? null : $complaint->sla_due_at->toDayDateTimeString();
        $this->reset(['categoryId', 'subject', 'description', 'severity', 'admissionNumber', 'suspectedSafeguardingConcern', 'anonymous']);
        $this->toast(__('Complaint received.'));
    }

    public function render(): View
    {
        [$type, $raiserId] = $this->resolveRaiser();

        $mine = $raiserId !== null
            ? Complaint::where('school_id', $this->school->id)->where('raised_by_type', $type)->where('raised_by_id', $raiserId)
                ->orderByDesc('id')->limit(20)
                ->get(['id', 'complaint_number', 'subject', 'status', 'sla_due_at', 'safeguarding_concern_id'])
            : collect();

        $threads = [];
        $reader = app(GetComplaintThreadForRaiserAction::class);

        foreach ($mine as $complaint) {
            if (! $complaint->isRoutedToSafeguarding()) {
                $threads[$complaint->id] = $reader->execute($complaint->id);
            }
        }

        return view('comms::complaints.submit', [
            'categories' => ComplaintCategory::where('school_id', $this->school->id)->orderBy('name')->get(['id', 'name', 'sla_hours', 'is_safeguarding_trigger']),
            'mine' => $mine,
            'threads' => $threads,
        ]);
    }

    /**
     * @return array{0: string, 1: int|null}
     */
    private function resolveRaiser(): array
    {
        $userId = (int) auth()->id();

        $staff = Staff::where('school_id', $this->school->id)->where('user_id', $userId)->first();

        if ($staff !== null) {
            return ['staff', $staff->id];
        }

        $guardian = Guardian::where('school_id', $this->school->id)->where('user_id', $userId)->first();

        return $guardian !== null ? ['guardian', $guardian->id] : ['anonymous', null];
    }
}
