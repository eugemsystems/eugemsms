<?php

declare(strict_types=1);

namespace Modules\Compliance\Livewire\Privacy\Requests;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Compliance\Domain\Actions\CompileSubjectAccessResponseAction;
use Modules\Compliance\Domain\Actions\ReceiveSubjectAccessRequestAction;
use Modules\Compliance\Domain\Actions\RefuseSubjectAccessRequestAction;
use Modules\Compliance\Domain\Actions\VerifyRequesterIdentityAction;
use Modules\Compliance\Domain\DataObjects\ReceiveSubjectAccessRequestData;
use Modules\Compliance\Domain\Exceptions\IdentityNotVerifiedException;
use Modules\Compliance\Models\SubjectAccessRequest;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\School;

/**
 * `Compliance\Privacy\Requests` (Book H3 CMP-03 §4 ⚠, `privacy.request.handle`).
 * Identity verification is enforced in this literal order
 * (BR-CMP-03-008): a request compiled before verification refuses
 * outright (`IdentityNotVerifiedException`), this screen simply
 * disables the compile button until `identity_verified` is true
 * rather than letting the click fail. The compiled response itself
 * (`CompileSubjectAccessResponseAction`) already excludes safeguarding
 * and medical records and any third party's own personal data by
 * construction (BR-CMP-03-009) — this screen renders whatever comes
 * back without adding anything else.
 */
#[Title('Subject access requests')]
#[Layout('layouts.app')]
final class Index extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;
    use Toasts;

    public string $requestType = 'access';

    public string $subjectType = 'student';

    public string $subjectId = '';

    public string $requesterName = '';

    public string $requesterRelationship = '';

    public string $scopeDescription = '';

    public ?int $selectedRequestId = null;

    public string $verificationMethod = '';

    /** @var array<string, mixed>|null */
    public ?array $compiledResponse = null;

    public string $refusalGrounds = '';

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('privacy.request.handle');
    }

    public function receive(): void
    {
        $this->authorizePermission('privacy.request.handle');

        $this->validate([
            'requestType' => ['required', 'in:access,correction,erasure,portability,objection,restriction'],
            'subjectType' => ['required', 'in:student,guardian,staff'],
            'requesterName' => ['required', 'string', 'max:200'],
            'scopeDescription' => ['required', 'string'],
        ]);

        app(ReceiveSubjectAccessRequestAction::class)->execute(new ReceiveSubjectAccessRequestData(
            schoolId: $this->school->id,
            requestType: $this->requestType,
            subjectType: $this->subjectType,
            subjectId: $this->subjectId !== '' ? (int) $this->subjectId : null,
            requesterName: $this->requesterName,
            scopeDescription: $this->scopeDescription,
            requesterRelationship: $this->requesterRelationship !== '' ? $this->requesterRelationship : null,
        ));

        $this->reset(['subjectId', 'requesterName', 'requesterRelationship', 'scopeDescription']);
        $this->toast(__('Request received — statutory due date set.'));
    }

    public function verify(int $requestId): void
    {
        $this->authorizePermission('privacy.request.handle');

        $this->validate(['verificationMethod' => ['required', 'string', 'max:60']]);

        app(VerifyRequesterIdentityAction::class)->execute($requestId, (int) auth()->id(), $this->verificationMethod);

        $this->reset(['verificationMethod']);
        $this->toast(__('Identity verified.'));
    }

    public function compile(int $requestId): void
    {
        $this->authorizePermission('privacy.request.handle');

        try {
            $this->compiledResponse = app(CompileSubjectAccessResponseAction::class)->execute($requestId);
            $this->selectedRequestId = $requestId;
        } catch (IdentityNotVerifiedException $e) {
            $this->toast($e->getMessage(), 'danger');
        }
    }

    public function refuse(int $requestId): void
    {
        $this->authorizePermission('privacy.request.handle');

        $this->validate(['refusalGrounds' => ['required', 'string', 'min:5', 'max:255']]);

        app(RefuseSubjectAccessRequestAction::class)->execute($requestId, (int) auth()->id(), $this->refusalGrounds);

        $this->reset(['refusalGrounds']);
        $this->toast(__('Request refused with grounds recorded.'));
    }

    public function render(): View
    {
        return view('compliance::privacy.requests.index', [
            'requests' => SubjectAccessRequest::where('school_id', $this->school->id)->orderBy('due_by')->get(),
        ]);
    }
}
