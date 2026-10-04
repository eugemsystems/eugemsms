<?php

declare(strict_types=1);

namespace Modules\Boarding\Livewire\Hostels;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Boarding\Domain\Actions\CreateHostelAction;
use Modules\Boarding\Domain\Actions\CreateHostelBedAction;
use Modules\Boarding\Domain\Actions\CreateHostelRoomAction;
use Modules\Boarding\Domain\Actions\CreateHostelWingAction;
use Modules\Boarding\Domain\Actions\MarkRoomOutOfServiceAction;
use Modules\Boarding\Domain\Actions\RecalculateHostelCapacityAction;
use Modules\Boarding\Domain\DataObjects\CreateHostelBedData;
use Modules\Boarding\Domain\DataObjects\CreateHostelData;
use Modules\Boarding\Domain\DataObjects\CreateHostelRoomData;
use Modules\Boarding\Domain\DataObjects\CreateHostelWingData;
use Modules\Boarding\Domain\DataObjects\MarkRoomOutOfServiceData;
use Modules\Boarding\Models\Hostel;
use Modules\Core\Domain\Exceptions\DomainException;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\School;
use Modules\People\Models\Staff;

/**
 * `Hostels\Structure` (Book F BRD-01 §5, `boarding.hostel.view` to
 * browse, `boarding.hostel.manage` to create/mutate). The hierarchy
 * screen: hostel → wing → room → bed, in one page — mirrors
 * `Curriculum\Frameworks`'s list+create shape, repeated per level.
 * `hostels.capacity` is never typed in directly anywhere on this
 * screen (BR-BRD-01-002); `CreateHostelBedAction` recomputes it
 * automatically and a manual "Recalculate" button is offered only as
 * a correction tool after a bed's own condition changes.
 */
#[Title('Hostel structure')]
#[Layout('layouts.app')]
final class Structure extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;
    use Toasts;

    public ?int $selectedHostelId = null;

    public string $hostelCode = '';

    public string $hostelName = '';

    public string $hostelGender = 'male';

    public ?int $housemasterStaffId = null;

    public ?int $matronStaffId = null;

    public string $wingCode = '';

    public string $wingName = '';

    public string $roomNumber = '';

    public string $roomType = 'dormitory';

    public int $bedCount = 1;

    public ?int $wingId = null;

    public bool $isGroundFloor = false;

    public string $proximityToExit = '';

    public ?int $bedRoomId = null;

    public string $bedNumber = '';

    public string $bedType = 'single';

    public ?int $outOfServiceRoomId = null;

    public string $outOfServiceReason = '';

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('boarding.hostel.view');
    }

    public function selectHostel(int $hostelId): void
    {
        $this->selectedHostelId = $hostelId;
    }

    public function createHostel(): void
    {
        $this->authorizePermission('boarding.hostel.manage');

        $this->validate([
            'hostelCode' => ['required', 'string', 'max:20'],
            'hostelName' => ['required', 'string', 'max:120'],
            'hostelGender' => ['required', 'in:male,female'],
        ]);

        $hostel = app(CreateHostelAction::class)->execute(new CreateHostelData(
            schoolId: $this->school->id,
            code: $this->hostelCode,
            name: $this->hostelName,
            gender: $this->hostelGender,
            createdBy: (int) auth()->id(),
            housemasterStaffId: $this->housemasterStaffId,
            matronStaffId: $this->matronStaffId,
        ));

        $this->reset(['hostelCode', 'hostelName', 'housemasterStaffId', 'matronStaffId']);
        $this->selectedHostelId = $hostel->id;
        $this->toast(__('Hostel created.'));
    }

    public function createWing(): void
    {
        $this->authorizePermission('boarding.hostel.manage');

        if ($this->selectedHostelId === null) {
            return;
        }

        $this->validate(['wingCode' => ['required', 'string', 'max:20'], 'wingName' => ['required', 'string', 'max:80']]);

        app(CreateHostelWingAction::class)->execute(new CreateHostelWingData(
            schoolId: $this->school->id,
            hostelId: $this->selectedHostelId,
            code: $this->wingCode,
            name: $this->wingName,
        ));

        $this->reset(['wingCode', 'wingName']);
        $this->toast(__('Wing added.'));
    }

    public function createRoom(): void
    {
        $this->authorizePermission('boarding.hostel.manage');

        if ($this->selectedHostelId === null) {
            return;
        }

        $this->validate([
            'roomNumber' => ['required', 'string', 'max:20'],
            'roomType' => ['required', 'in:dormitory,cubicle,single,prefect,isolation,staff'],
            'bedCount' => ['required', 'integer', 'min:1', 'max:30'],
        ]);

        app(CreateHostelRoomAction::class)->execute(new CreateHostelRoomData(
            schoolId: $this->school->id,
            hostelId: $this->selectedHostelId,
            roomNumber: $this->roomNumber,
            roomType: $this->roomType,
            bedCount: $this->bedCount,
            wingId: $this->wingId,
            isGroundFloor: $this->isGroundFloor,
            proximityToExit: $this->proximityToExit !== '' ? $this->proximityToExit : null,
        ));

        $this->reset(['roomNumber', 'bedCount', 'wingId', 'isGroundFloor', 'proximityToExit']);
        $this->toast(__('Room added.'));
    }

    public function createBed(): void
    {
        $this->authorizePermission('boarding.hostel.manage');

        if ($this->bedRoomId === null) {
            return;
        }

        $this->validate(['bedNumber' => ['required', 'string', 'max:20'], 'bedType' => ['required', 'in:single,bunk_upper,bunk_lower']]);

        app(CreateHostelBedAction::class)->execute(new CreateHostelBedData(
            schoolId: $this->school->id,
            roomId: $this->bedRoomId,
            bedNumber: $this->bedNumber,
            bedType: $this->bedType,
        ));

        $this->reset(['bedNumber', 'bedRoomId']);
        $this->toast(__('Bed added — hostel capacity recalculated.'));
    }

    public function markOutOfService(int $roomId): void
    {
        $this->authorizePermission('boarding.hostel.manage');

        if (trim($this->outOfServiceReason) === '') {
            $this->toast(__('A reason is required to mark a room out of service.'), 'danger');

            return;
        }

        try {
            app(MarkRoomOutOfServiceAction::class)->execute(new MarkRoomOutOfServiceData(
                roomId: $roomId,
                reason: $this->outOfServiceReason,
            ));
        } catch (DomainException $e) {
            $this->toast($e->getMessage(), 'danger');

            return;
        }

        $this->reset(['outOfServiceRoomId', 'outOfServiceReason']);
        $this->toast(__('Room marked out of service.'));
    }

    public function recalculateCapacity(int $hostelId): void
    {
        $this->authorizePermission('boarding.hostel.manage');

        app(RecalculateHostelCapacityAction::class)->execute($hostelId);
        $this->toast(__('Capacity recalculated from active beds.'));
    }

    public function render(): View
    {
        $selectedHostel = $this->selectedHostelId !== null
            ? Hostel::with(['wings', 'rooms.beds', 'rooms.wing'])->find($this->selectedHostelId)
            : null;

        return view('boarding::hostels.structure', [
            'hostels' => Hostel::where('school_id', $this->school->id)->orderBy('name')->get(),
            'selectedHostel' => $selectedHostel,
            'staffOptions' => Staff::where('school_id', $this->school->id)->get(['id', 'first_name', 'last_name']),
        ]);
    }
}
