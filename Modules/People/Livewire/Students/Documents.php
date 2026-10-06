<?php

declare(strict_types=1);

namespace Modules\People\Livewire\Students;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
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
use Modules\People\Domain\Actions\AttachStudentDocumentAction;
use Modules\People\Domain\Actions\VerifyStudentDocumentAction;
use Modules\People\Domain\DataObjects\AttachStudentDocumentData;
use Modules\People\Domain\DataObjects\VerifyStudentDocumentData;
use Modules\People\Livewire\Concerns\UploadsToVault;
use Modules\People\Models\Student;
use Modules\People\Models\StudentDocument;

/**
 * `People\Students\Documents` (Book C PPL-01 §8, `people.students.document_manage`). Identity, permit and transfer documents on a learner's file, with expiry indicators. Verifying needs the original to have been sighted or says so; an expired document cannot be verified.
 */
#[Title('Learner documents')]
#[Layout('layouts.app')]
final class Documents extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;
    use Toasts;

    public Student $student;

    public function mount(School $school, Student $student): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('people.students.document_manage');

        abort_unless($student->school_id === $school->id, 404);

        $this->student = $student;
    }

    use UploadsToVault;
    use WithFileUploads;

    public string $documentType = 'birth_certificate';

    public ?TemporaryUploadedFile $file = null;

    public string $referenceNumber = '';

    public string $issuedOn = '';

    public string $expiresOn = '';

    public function attach(): void
    {
        $this->authorizePermission('people.students.document_manage');
        $this->resetErrorBag();
        $this->validate(['documentType' => ['required', 'string'], 'file' => ['required', 'file', 'max:10240'], 'issuedOn' => ['nullable', 'date'], 'expiresOn' => ['nullable', 'date']]);

        $fileId = $this->storeInVault($this->file, 'student_document', 'file', $this->expiresOn !== '' ? Carbon::parse($this->expiresOn) : null);

        if ($fileId === null) {
            return;
        }

        try {
            app(AttachStudentDocumentAction::class)->execute(new AttachStudentDocumentData(
                schoolId: $this->school->id, studentId: $this->student->id, documentType: $this->documentType, fileId: $fileId, uploadedByUserId: (int) auth()->id(),
                referenceNumber: $this->referenceNumber === '' ? null : $this->referenceNumber,
                issuedOn: $this->issuedOn === '' ? null : Carbon::parse($this->issuedOn), expiresOn: $this->expiresOn === '' ? null : Carbon::parse($this->expiresOn),
            ));
        } catch (DomainException|InvalidArgumentException $exception) {
            $this->addError('file', $exception->getMessage());

            return;
        }

        $this->reset('file', 'referenceNumber', 'issuedOn', 'expiresOn');
        $this->toast(__('Document attached.'));
    }

    public function verify(int $documentId, bool $originalSighted): void
    {
        $this->authorizePermission('people.students.document_manage');

        $document = StudentDocument::query()->where('student_id', $this->student->id)->find($documentId);

        if ($document === null) {
            abort(404);
        }

        try {
            app(VerifyStudentDocumentAction::class)->execute(new VerifyStudentDocumentData($document->id, (int) auth()->id(), $originalSighted));
        } catch (DomainException|InvalidArgumentException $exception) {
            $this->toast($exception->getMessage(), 'danger');

            return;
        }

        $this->toast(__('Verified.'));
    }

    public function render(): View
    {
        return view('people::students.documents', [
            'documents' => StudentDocument::query()->where('student_id', $this->student->id)->orderByDesc('id')->get(),
            'types' => AttachStudentDocumentAction::TYPES,
        ]);
    }
}
