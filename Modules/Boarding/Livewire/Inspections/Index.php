<?php

declare(strict_types=1);

namespace Modules\Boarding\Livewire\Inspections;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Boarding\Domain\Actions\RecordRoomInspectionAction;
use Modules\Boarding\Domain\DataObjects\RecordRoomInspectionData;
use Modules\Boarding\Models\Hostel;
use Modules\Boarding\Models\RoomInspection;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Livewire\Sessions\Concerns\InteractsWithSession;
use Modules\Core\Models\School;
use Modules\People\Models\Staff;

/**
 * `Inspections\Index` (Book F BRD-01 §5, `boarding.inspection.manage`
 * to record, `boarding.inspection.view` to browse). A plain scored
 * form rather than the spec's own mobile-first photo-capture flow —
 * `photo_file_ids` accepts a JSON array of already-uploaded `files.id`
 * values (CORE-10), not a camera widget this pass doesn't build.
 */
#[Title('Room inspections')]
#[Layout('layouts.app')]
final class Index extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;
    use InteractsWithSession;
    use Toasts;

    public ?int $hostelId = null;

    public ?int $roomId = null;

    public string $inspectionType = 'routine';

    /** @var array<string, int> */
    public array $criteriaScores = ['tidiness' => 8, 'cleanliness' => 8, 'maintenance' => 8];

    public float $maxScore = 30;

    public ?string $findings = '';

    public bool $followUpRequired = false;

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->loadSessionContext($school);
        $this->authorizePermission('boarding.inspection.view');
    }

    public function record(): void
    {
        $this->authorizePermission('boarding.inspection.manage');

        if ($this->roomId === null) {
            $this->toast(__('Pick a room first.'), 'danger');

            return;
        }

        $staffId = Staff::where('school_id', $this->school->id)->where('user_id', Auth::id())->value('id');

        if ($staffId === null) {
            $this->toast(__('Your account has no staff record linked at this school.'), 'danger');

            return;
        }

        $term = $this->school->currentAcademicYear()?->currentTerm();

        if ($term === null) {
            $this->toast(__('No current term is set.'), 'danger');

            return;
        }

        app(RecordRoomInspectionAction::class)->execute(new RecordRoomInspectionData(
            schoolId: $this->school->id,
            termId: $term->id,
            roomId: $this->roomId,
            inspectionDate: Carbon::now(),
            inspectionType: $this->inspectionType,
            criteriaScores: $this->criteriaScores,
            maxScore: $this->maxScore,
            inspectorStaffId: $staffId,
            findings: $this->findings !== '' ? $this->findings : null,
            followUpRequired: $this->followUpRequired,
        ));

        $this->reset(['findings', 'followUpRequired']);
        $this->toast(__('Inspection recorded.'));
    }

    public function render(): View
    {
        $hostel = $this->hostelId !== null ? Hostel::find($this->hostelId) : null;
        $rooms = $hostel !== null ? $hostel->rooms : collect();

        return view('boarding::inspections.index', [
            'hostels' => Hostel::where('school_id', $this->school->id)->orderBy('name')->get(),
            'rooms' => $rooms,
            'inspections' => RoomInspection::where('school_id', $this->school->id)->with('room.hostel', 'inspector')->orderByDesc('inspection_date')->limit(30)->get(),
        ]);
    }
}
