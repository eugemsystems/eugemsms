<?php

declare(strict_types=1);

namespace Modules\People\Livewire\Staff;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\School;
use Modules\People\Domain\Actions\CreateStaffAction;
use Modules\People\Domain\DataObjects\CreateStaffData;
use Modules\People\Models\Department;
use Modules\People\Models\EstablishmentPost;

/**
 * `People\Staff\Create` (Book C PPL-04 §5, `people.staff.create`).
 * Create-only — no `UpdateStaffAction` exists in the domain layer
 * (verified: `ls Modules/People/Domain/Actions/ | grep ^Update` only
 * returns PPL-01's `UpdateStudentProfileAction`). A practical subset
 * of the 60-column `staff` table, not an exhaustive form — same
 * precedent as `People\Students\Create`.
 */
#[Title('New staff member')]
#[Layout('layouts.app')]
final class Create extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;

    public string $title = '';

    public string $firstName = '';

    public string $lastName = '';

    public string $dateOfBirth = '';

    public string $gender = 'male';

    public string $primaryPhone = '';

    public string $staffCategory = 'teaching';

    public string $joinedOn = '';

    public ?int $departmentId = null;

    public ?int $postId = null;

    public bool $isTeaching = false;

    public string $teacherRegistrationNo = '';

    public string $maxWeeklyPeriods = '';

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('people.staff.create');

        $this->joinedOn = now()->toDateString();
    }

    public function save(): void
    {
        $this->validate([
            'firstName' => ['required', 'string', 'max:80'],
            'lastName' => ['required', 'string', 'max:80'],
            'dateOfBirth' => ['required', 'date', 'before:today'],
            'primaryPhone' => ['required', 'string', 'max:30'],
            'staffCategory' => ['required', 'string'],
            'joinedOn' => ['required', 'date'],
        ]);

        $staff = app(CreateStaffAction::class)->execute(new CreateStaffData(
            schoolId: $this->school->id,
            firstName: $this->firstName,
            lastName: $this->lastName,
            dateOfBirth: Carbon::parse($this->dateOfBirth),
            gender: $this->gender,
            primaryPhone: $this->primaryPhone,
            staffCategory: $this->staffCategory,
            joinedOn: Carbon::parse($this->joinedOn),
            createdByUserId: (int) Auth::id(),
            title: $this->title !== '' ? $this->title : null,
            departmentId: $this->departmentId,
            postId: $this->postId,
            isTeaching: $this->isTeaching,
            teacherRegistrationNo: $this->teacherRegistrationNo !== '' ? $this->teacherRegistrationNo : null,
            maxWeeklyPeriods: $this->maxWeeklyPeriods !== '' ? (int) $this->maxWeeklyPeriods : null,
        ));

        $this->redirectRoute('people.staff.show', ['school' => $this->school, 'staff' => $staff], navigate: true);
    }

    public function render(): View
    {
        return view('people::staff.create', [
            'departments' => Department::where('school_id', $this->school->id)->orderBy('name')->get(),
            'posts' => EstablishmentPost::where('school_id', $this->school->id)->where('is_active', true)->orderBy('title')->get(),
        ]);
    }
}
