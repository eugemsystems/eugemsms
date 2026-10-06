<?php

declare(strict_types=1);

namespace Modules\People\Livewire\Admissions\Applications;

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
use Modules\People\Domain\Actions\AttachApplicationDocumentAction;
use Modules\People\Domain\Actions\AttachStudentDocumentAction;
use Modules\People\Domain\Actions\VerifyApplicationDocumentAction;
use Modules\People\Domain\DataObjects\AttachApplicationDocumentData;
use Modules\People\Domain\DataObjects\VerifyApplicationDocumentData;
use Modules\People\Livewire\Concerns\UploadsToVault;
use Modules\People\Models\Application;
use Modules\People\Models\ApplicationDocument;

/**
 * `Admissions\Applications\Documents` (Book C PPL-02 §5,
 * `people.admissions.application_review`). Supporting documents for an
 * application, verified one by one. Once the application has become a learner
 * its documents belong on the learner's file.
 */
#[Title('Application documents')]
#[Layout('layouts.app')]
final class Documents extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;
    use Toasts;
    use UploadsToVault;
    use WithFileUploads;

    public Application $application;

    public string $documentType = 'birth_certificate';

    public ?TemporaryUploadedFile $file = null;

    public function mount(School $school, Application $application): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('people.admissions.application_review');

        abort_unless($application->school_id === $school->id, 404);

        $this->application = $application;
    }

    public function attach(): void
    {
        $this->authorizePermission('people.admissions.application_review');
        $this->resetErrorBag();
        $this->validate(['file' => ['required', 'file', 'max:10240']]);

        $fileId = $this->storeInVault($this->file, 'application_document');

        if ($fileId === null) {
            return;
        }

        try {
            app(AttachApplicationDocumentAction::class)->execute(new AttachApplicationDocumentData($this->school->id, $this->application->id, $this->documentType, $fileId));
        } catch (DomainException|InvalidArgumentException $exception) {
            $this->addError('file', $exception->getMessage());

            return;
        }

        $this->reset('file');
        $this->toast(__('Document attached.'));
    }

    public function verify(int $documentId): void
    {
        $this->authorizePermission('people.admissions.application_review');

        $document = ApplicationDocument::query()->where('application_id', $this->application->id)->findOrFail($documentId);
        app(VerifyApplicationDocumentAction::class)->execute(new VerifyApplicationDocumentData($document->id, (int) auth()->id()));
        $this->toast(__('Verified.'));
    }

    public function render(): View
    {
        return view('people::admissions.application-documents', ['documents' => ApplicationDocument::query()->where('application_id', $this->application->id)->orderByDesc('id')->get(), 'types' => AttachStudentDocumentAction::TYPES]);
    }
}
