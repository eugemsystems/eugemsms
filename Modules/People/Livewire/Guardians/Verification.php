<?php

declare(strict_types=1);

namespace Modules\People\Livewire\Guardians;

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
use Modules\People\Domain\Actions\RecordGuardianVerificationAction;
use Modules\People\Domain\Actions\VerifyGuardianAction;
use Modules\People\Domain\DataObjects\RecordGuardianVerificationData;
use Modules\People\Domain\DataObjects\VerifyGuardianData;
use Modules\People\Livewire\Concerns\UploadsToVault;
use Modules\People\Models\Guardian;
use Modules\People\Models\GuardianVerification;

/**
 * `People\Guardians\Verification` (Book C PPL-03 §7, `people.guardians.verify`).
 * The ID document and the collection photo the gate shows before a child is
 * released. Recording and verifying are separate steps by separate people: a
 * guardian cannot verify themselves, and both files must be on record first.
 */
#[Title('Guardian verification')]
#[Layout('layouts.app')]
final class Verification extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;
    use Toasts;
    use UploadsToVault;
    use WithFileUploads;

    public string $search = '';

    public ?int $guardianId = null;

    public string $documentType = 'national_id';

    public ?TemporaryUploadedFile $document = null;

    public ?TemporaryUploadedFile $photo = null;

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('people.guardians.verify');
    }

    public function select(int $id): void
    {
        $this->authorizePermission('people.guardians.verify');
        $this->guardianId = Guardian::query()->whereKey($id)->value('id');
        $this->search = '';
    }

    public function record(): void
    {
        $this->authorizePermission('people.guardians.verify');
        $this->resetErrorBag();
        $this->validate(['document' => ['required', 'file', 'max:10240'], 'photo' => ['required', 'image', 'max:5120']]);

        if ($this->guardianId === null) {
            $this->addError('guardianId', __('Choose a guardian.'));

            return;
        }

        $documentId = $this->storeInVault($this->document, 'guardian_verification', 'document');
        $photoId = $documentId === null ? null : $this->storeInVault($this->photo, 'guardian_verification', 'photo');

        if ($documentId === null || $photoId === null) {
            return;
        }

        try {
            app(RecordGuardianVerificationAction::class)->execute(new RecordGuardianVerificationData($this->school->id, $this->guardianId, $this->documentType, $documentId, $photoId));
        } catch (DomainException|InvalidArgumentException $exception) {
            $this->addError('guardianId', $exception->getMessage());

            return;
        }

        $this->reset('document', 'photo');
        $this->toast(__('Recorded. Someone else must now verify it.'));
    }

    public function verify(int $verificationId): void
    {
        $this->authorizePermission('people.guardians.verify');

        try {
            app(VerifyGuardianAction::class)->execute(new VerifyGuardianData(GuardianVerification::query()->findOrFail($verificationId)->id, (int) auth()->id()));
        } catch (DomainException|InvalidArgumentException $exception) {
            $this->toast($exception->getMessage(), 'danger');

            return;
        }

        $this->toast(__('Verified.'));
    }

    public function render(): View
    {
        $term = trim($this->search);
        $like = '%'.addcslashes($term, '%_\\').'%';
        $rows = GuardianVerification::query()->orderByDesc('id')->limit(60)->get();

        return view('people::guardians.verification', [
            'rows' => $rows,
            'names' => Guardian::query()->whereIn('id', $rows->pluck('guardian_id'))->get()->mapWithKeys(fn (Guardian $g): array => [$g->id => $g->displayName()]),
            'matches' => mb_strlen($term) < 2 ? collect() : Guardian::query()->where(fn ($q) => $q->where('first_name', 'like', $like)->orWhere('last_name', 'like', $like)->orWhere('primary_phone', 'like', $like))->limit(8)->get(),
            'picked' => $this->guardianId === null ? null : Guardian::query()->find($this->guardianId),
        ]);
    }
}
