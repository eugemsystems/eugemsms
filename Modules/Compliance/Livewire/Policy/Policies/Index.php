<?php

declare(strict_types=1);

namespace Modules\Compliance\Livewire\Policy\Policies;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Compliance\Domain\Actions\AcknowledgePolicyAction;
use Modules\Compliance\Domain\Actions\CheckPolicyReviewDueAction;
use Modules\Compliance\Domain\Actions\CreatePolicyAction;
use Modules\Compliance\Domain\Actions\ReportPolicyAcknowledgementStatusAction;
use Modules\Compliance\Domain\DataObjects\AcknowledgePolicyData;
use Modules\Compliance\Domain\DataObjects\CreatePolicyData;
use Modules\Compliance\Models\Policy;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\School;

/**
 * `Compliance\Policy\Policies` (Book H3 CMP-04, `policy.manage` to
 * create/check, `policy.view` to view — no spec screens table exists
 * for CMP-04, see this book's own `11-book-h3-...md`; this permission
 * naming and screen shape is this pass's own design). A new version is
 * a NEW row (`CreatePolicyAction` also supersedes the old one,
 * BR-CMP-04-001) — never edited in place. Acknowledgement status
 * (`ReportPolicyAcknowledgementStatusAction`) and the review-due scan
 * (`CheckPolicyReviewDueAction`, an on-demand stand-in for a scheduled
 * job, matching this book's own established pattern) are both exposed
 * per-policy rather than given their own routes.
 */
#[Title('Policy register')]
#[Layout('layouts.app')]
final class Index extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;
    use Toasts;

    public string $code = '';

    public string $title = '';

    public string $category = '';

    public string $version = '1';

    public string $effectiveFrom = '';

    public bool $requiresAcknowledgement = false;

    /** @var array<int, string> */
    public array $acknowledgementAudiences = [];

    public string $reviewDueOn = '';

    public ?int $supersedesPolicyId = null;

    public ?int $selectedPolicyId = null;

    /** @var array<string, array{acknowledged: array<int, int>, outstanding: array<int, int>}>|null */
    public ?array $acknowledgementStatus = null;

    public int $overdueCount = 0;

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('policy.view');

        $this->effectiveFrom = now()->toDateString();
    }

    public function create(): void
    {
        $this->authorizePermission('policy.manage');

        $this->validate([
            'code' => ['required', 'string', 'max:40'],
            'title' => ['required', 'string', 'max:200'],
            'category' => ['required', 'string', 'max:60'],
            'version' => ['required', 'string', 'max:20'],
            'effectiveFrom' => ['required', 'date'],
        ]);

        app(CreatePolicyAction::class)->execute(new CreatePolicyData(
            schoolId: $this->school->id,
            code: $this->code,
            title: $this->title,
            category: $this->category,
            version: $this->version,
            effectiveFrom: $this->effectiveFrom,
            requiresAcknowledgement: $this->requiresAcknowledgement,
            acknowledgementAudiences: $this->acknowledgementAudiences,
            reviewDueOn: $this->reviewDueOn !== '' ? $this->reviewDueOn : null,
            supersedesPolicyId: $this->supersedesPolicyId,
        ));

        $this->reset(['code', 'title', 'category', 'reviewDueOn', 'supersedesPolicyId', 'acknowledgementAudiences', 'requiresAcknowledgement']);
        $this->toast(__('Policy version created.'));
    }

    public function acknowledge(int $policyId): void
    {
        app(AcknowledgePolicyAction::class)->execute(new AcknowledgePolicyData(
            policyId: $policyId,
            acknowledgedByType: 'staff',
            acknowledgedById: (int) auth()->id(),
            method: 'portal',
        ));

        $this->toast(__('Acknowledgement recorded.'));
    }

    public function viewStatus(int $policyId): void
    {
        $this->authorizePermission('policy.view');

        $this->selectedPolicyId = $policyId;
        $this->acknowledgementStatus = app(ReportPolicyAcknowledgementStatusAction::class)->execute($policyId);
    }

    public function checkReviewDue(): void
    {
        $this->authorizePermission('policy.manage');

        $this->overdueCount = app(CheckPolicyReviewDueAction::class)->execute($this->school->id)->count();
    }

    public function render(): View
    {
        return view('compliance::policy.policies.index', [
            'policies' => Policy::where('school_id', $this->school->id)->orderByDesc('id')->get(),
        ]);
    }
}
