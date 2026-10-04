<?php

declare(strict_types=1);

namespace Modules\People\Livewire\Staff;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;
use Modules\Core\Domain\Actions\Files\UploadFileAction;
use Modules\Core\Domain\DataObjects\Files\UploadFileData;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\School;
use Modules\People\Domain\Actions\AddStaffDocumentAction;
use Modules\People\Domain\Actions\CheckStaffDocumentExpiryAction;
use Modules\People\Domain\DataObjects\AddStaffDocumentData;
use Modules\People\Models\Staff;

/**
 * `People\Staff\Compliance` (Book C PPL-04 §5/§4/BR-PPL-04-018,
 * `people.staff.document_manage`). Reads straight off
 * `CheckStaffDocumentExpiryAction`'s own return shape rather than
 * re-querying `StaffDocument` separately, so this dashboard can never
 * drift from what that Action considers "expiring" (AC-PPL-04-006).
 */
#[Title('Document compliance')]
#[Layout('layouts.app')]
final class Compliance extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;
    use Toasts;
    use WithFileUploads;

    public ?int $staffId = null;

    public string $documentType = 'police_clearance';

    public ?TemporaryUploadedFile $file = null;

    public string $referenceNumber = '';

    public string $issuedOn = '';

    public string $expiresOn = '';

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('people.staff.document_manage');
    }

    public function addDocument(): void
    {
        $this->validate([
            'staffId' => ['required', 'integer'],
            'documentType' => ['required', 'string'],
            'file' => ['required', 'file', 'max:10240'],
            'expiresOn' => ['nullable', 'date'],
        ]);

        $contents = $this->file?->get();

        if ($contents === false || $contents === null) {
            $this->addError('file', __('The uploaded file could not be read — please try again.'));

            return;
        }

        $uploaded = app(UploadFileAction::class)->execute(new UploadFileData(
            schoolId: $this->school->id,
            category: 'staff_document',
            contents: $contents,
            originalName: $this->file->getClientOriginalName(),
            uploadedByUserId: (int) Auth::id(),
            attachableType: 'staff_document',
            expiresOn: $this->expiresOn !== '' ? Carbon::parse($this->expiresOn) : null,
        ));

        app(AddStaffDocumentAction::class)->execute(new AddStaffDocumentData(
            schoolId: $this->school->id,
            staffId: (int) $this->staffId,
            documentType: $this->documentType,
            fileId: $uploaded->id,
            referenceNumber: $this->referenceNumber !== '' ? $this->referenceNumber : null,
            issuedOn: $this->issuedOn !== '' ? Carbon::parse($this->issuedOn) : null,
            expiresOn: $this->expiresOn !== '' ? Carbon::parse($this->expiresOn) : null,
        ));

        $this->reset(['staffId', 'file', 'referenceNumber', 'issuedOn', 'expiresOn']);
        $this->toast(__('Document added.'));
    }

    public function render(): View
    {
        return view('people::staff.compliance', [
            'staffList' => Staff::where('school_id', $this->school->id)->orderBy('first_name')->get(),
            'expiring' => app(CheckStaffDocumentExpiryAction::class)->execute($this->school->id),
        ]);
    }
}
