<?php

declare(strict_types=1);

namespace Modules\Compliance\Livewire\Policy\StatutoryDocuments;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Compliance\Domain\Actions\CheckDocumentExpiryAction;
use Modules\Compliance\Domain\Actions\CreateContractAction;
use Modules\Compliance\Domain\Actions\CreateStatutoryDocumentAction;
use Modules\Compliance\Domain\DataObjects\CreateContractData;
use Modules\Compliance\Domain\DataObjects\CreateStatutoryDocumentData;
use Modules\Compliance\Models\Contract;
use Modules\Compliance\Models\StatutoryDocument;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\School;

/**
 * `Compliance\Policy\StatutoryDocuments` (Book H3 CMP-04, `policy.manage`).
 * Named `StatutoryDocuments` rather than the more obvious `Documents`
 * to avoid colliding with `Modules\Core\Livewire\Documents\Index`'s
 * own relative path per this book's own standing collision check —
 * the nesting under `Policy\` would already have prevented the
 * collision, but the distinct name also reads better next to
 * `statutory_documents` the table. Folds the contract register onto
 * the same screen: both tables share the exact same expiry mechanism
 * (`CheckDocumentExpiryAction` recomputes `status` for both in one
 * call — see the module's own migration docblock on why `contracts`
 * reuses it rather than inventing a second one), so two cards on one
 * screen with a single "check expiry" button is more honest than two
 * routes with the same button duplicated.
 */
#[Title('Statutory documents & contracts')]
#[Layout('layouts.app')]
final class Index extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;
    use Toasts;

    public string $documentType = 'registration_certificate';

    public string $issuingAuthority = '';

    public string $referenceNumber = '';

    public string $issuedOn = '';

    public string $expiresOn = '';

    public string $renewalLeadDays = '60';

    public string $counterpartyName = '';

    public string $contractType = '';

    public string $startsOn = '';

    public string $contractExpiresOn = '';

    public string $contractRenewalLeadDays = '60';

    public int $criticalCount = 0;

    public bool $checked = false;

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('policy.manage');
    }

    public function createDocument(): void
    {
        $this->authorizePermission('policy.manage');

        $this->validate([
            'documentType' => ['required', 'in:registration_certificate,operating_licence,insurance,tax_clearance,health_inspection,fire_certificate,water_quality'],
            'issuingAuthority' => ['required', 'string', 'max:80'],
        ]);

        app(CreateStatutoryDocumentAction::class)->execute(new CreateStatutoryDocumentData(
            schoolId: $this->school->id,
            documentType: $this->documentType,
            issuingAuthority: $this->issuingAuthority,
            referenceNumber: $this->referenceNumber !== '' ? $this->referenceNumber : null,
            issuedOn: $this->issuedOn !== '' ? $this->issuedOn : null,
            expiresOn: $this->expiresOn !== '' ? $this->expiresOn : null,
            renewalLeadDays: (int) $this->renewalLeadDays,
        ));

        $this->reset(['issuingAuthority', 'referenceNumber', 'issuedOn', 'expiresOn']);
        $this->toast(__('Statutory document registered.'));
    }

    public function createContract(): void
    {
        $this->authorizePermission('policy.manage');

        $this->validate([
            'counterpartyName' => ['required', 'string', 'max:200'],
            'contractType' => ['required', 'string', 'max:60'],
            'startsOn' => ['required', 'date'],
        ]);

        app(CreateContractAction::class)->execute(new CreateContractData(
            schoolId: $this->school->id,
            counterpartyName: $this->counterpartyName,
            contractType: $this->contractType,
            startsOn: $this->startsOn,
            expiresOn: $this->contractExpiresOn !== '' ? $this->contractExpiresOn : null,
            renewalLeadDays: (int) $this->contractRenewalLeadDays,
        ));

        $this->reset(['counterpartyName', 'contractType', 'startsOn', 'contractExpiresOn']);
        $this->toast(__('Contract registered.'));
    }

    public function checkExpiry(): void
    {
        $this->authorizePermission('policy.manage');

        $result = app(CheckDocumentExpiryAction::class)->execute($this->school->id);

        $this->criticalCount = collect($result['statutory_documents'])
            ->filter(fn (StatutoryDocument $document): bool => $document->status === 'expired' && $document->isCritical())
            ->count();
        $this->checked = true;
    }

    public function render(): View
    {
        return view('compliance::policy.statutory-documents.index', [
            'documents' => StatutoryDocument::where('school_id', $this->school->id)->orderBy('expires_on')->get(),
            'contracts' => Contract::where('school_id', $this->school->id)->orderBy('expires_on')->get(),
        ]);
    }
}
