<?php

declare(strict_types=1);

namespace Modules\People\Livewire\Staff;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use InvalidArgumentException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;
use Modules\Core\Domain\Exceptions\DomainException;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\School;
use Modules\People\Domain\Actions\AddStaffQualificationAction;
use Modules\People\Domain\Actions\VerifyStaffQualificationAction;
use Modules\People\Domain\DataObjects\AddStaffQualificationData;
use Modules\People\Domain\DataObjects\VerifyStaffQualificationData;
use Modules\People\Livewire\Concerns\UploadsToVault;
use Modules\People\Models\Staff;
use Modules\People\Models\StaffQualification;

/**
 * `People\Staff\Qualifications` (Book C PPL-04 §5, `people.staff.qualification_manage`).
 * A staff member's qualifications, verified against the certificate by
 * someone other than the holder.
 */
#[Title('Staff qualifications')]
#[Layout('layouts.app')]
final class Qualifications extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;
    use Toasts;
    use UploadsToVault;
    use WithFileUploads;

    public Staff $staff;

    public string $type = 'degree';

    public string $title = '';

    public string $institution = '';

    public string $country = 'ZW';

    public string $year = '';

    public string $gradeClass = '';

    public string $subjects = '';

    public ?TemporaryUploadedFile $certificate = null;

    public function mount(School $school, Staff $staff): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('people.staff.qualification_manage');

        abort_unless($staff->school_id === $school->id, 404);

        $this->staff = $staff;
    }

    public function add(): void
    {
        $this->authorizePermission('people.staff.qualification_manage');
        $this->resetErrorBag();
        $this->validate(['title' => ['required', 'string', 'max:200'], 'institution' => ['required', 'string', 'max:200'], 'year' => ['nullable', 'integer'], 'certificate' => ['nullable', 'file', 'max:10240']]);

        $fileId = $this->certificate === null ? null : $this->storeInVault($this->certificate, 'staff_qualification', 'certificate');

        if ($this->certificate !== null && $fileId === null) {
            return;
        }

        try {
            app(AddStaffQualificationAction::class)->execute(new AddStaffQualificationData(
                schoolId: $this->school->id, staffId: $this->staff->id, qualificationType: $this->type, title: $this->title, institution: $this->institution, country: $this->country,
                yearObtained: $this->year === '' ? null : (int) $this->year, gradeClass: $this->gradeClass === '' ? null : $this->gradeClass,
                subjects: array_values(array_filter(array_map('trim', explode(',', $this->subjects)))), certificateFileId: $fileId,
            ));
        } catch (DomainException|InvalidArgumentException $exception) {
            $this->addError('title', $exception->getMessage());

            return;
        }

        $this->reset('title', 'institution', 'year', 'gradeClass', 'subjects', 'certificate');
        $this->toast(__('Qualification added.'));
    }

    public function verify(int $qualificationId): void
    {
        $this->authorizePermission('people.staff.qualification_manage');

        $qualification = StaffQualification::query()->where('staff_id', $this->staff->id)->findOrFail($qualificationId);

        try {
            app(VerifyStaffQualificationAction::class)->execute(new VerifyStaffQualificationData($qualification->id, (int) auth()->id()));
        } catch (DomainException|InvalidArgumentException $exception) {
            $this->toast($exception->getMessage(), 'danger');

            return;
        }

        $this->toast(__('Verified.'));
    }

    public function render(): View
    {
        return view('people::staff.qualifications', ['rows' => StaffQualification::query()->where('staff_id', $this->staff->id)->orderByDesc('year_obtained')->get(), 'types' => AddStaffQualificationAction::TYPES]);
    }
}
