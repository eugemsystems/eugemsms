<?php

declare(strict_types=1);

namespace Modules\Saas\Livewire\Vendor\Onboarding;

use App\Concerns\Toasts;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use InvalidArgumentException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Core\Domain\Exceptions\SerpException;
use Modules\Core\Domain\Support\Auth\UserType;
use Modules\Core\Domain\Support\Settings\ScopeChain;
use Modules\Core\Domain\Support\Settings\SettingResolver;
use Modules\Core\Models\School;
use Modules\Core\Models\Tenant;
use Modules\Saas\Domain\Actions\CompleteOnboardingStepAction;
use Modules\Saas\Domain\Actions\ListStalledOnboardingChecklistsAction;
use Modules\Saas\Domain\Actions\RecordVendorConsoleActionAction;
use Modules\Saas\Domain\Actions\StartOnboardingChecklistAction;
use Modules\Saas\Domain\DataObjects\StartOnboardingChecklistData;
use Modules\Saas\Livewire\Concerns\AuthorizesVendorConsole;
use Modules\Saas\Models\OnboardingChecklist;

/**
 * `Success\Onboarding\Index` (Book J SAA-03 §5, vendor console). Per-school
 * onboarding progress across tenants. A checklist with no progress for the
 * configured threshold shows as stalled and its success manager can be
 * alerted (BR-SAA-03-001). Starting one needs a school of the chosen tenant
 * that has none yet.
 */
#[Title('Onboarding tracker')]
#[Layout('saas::layouts.vendor')]
final class Index extends Component
{
    use AuthorizesVendorConsole;
    use Toasts;

    /** @var array<int, array{key: string, label: string}> */
    public const array DEFAULT_STEPS = [
        ['key' => 'school_profile', 'label' => 'School profile and calendar configured'],
        ['key' => 'import_learners', 'label' => 'Learners imported'],
        ['key' => 'import_staff', 'label' => 'Staff imported'],
        ['key' => 'fee_structure', 'label' => 'Fee structure set up'],
        ['key' => 'training', 'label' => 'Staff trained'],
        ['key' => 'go_live_check', 'label' => 'Go-live check passed'],
    ];

    public ?int $tenantId = null;

    public ?int $schoolId = null;

    public string $targetGoLive = '';

    public ?int $managerId = null;

    public function mount(): void
    {
        $this->authorizeVendor();
    }

    public function updatedTenantId(): void
    {
        $this->schoolId = null;
    }

    public function start(): void
    {
        $operator = $this->authorizeVendor();
        $this->resetErrorBag();

        $this->validate(['tenantId' => ['required', 'integer'], 'schoolId' => ['required', 'integer'], 'targetGoLive' => ['nullable', 'date']]);

        $tenant = Tenant::query()->findOrFail($this->tenantId);

        try {
            $checklist = app(StartOnboardingChecklistAction::class)->execute(new StartOnboardingChecklistData(
                tenantId: $tenant->id, schoolId: (int) $this->schoolId, steps: self::DEFAULT_STEPS,
                targetGoLiveDate: $this->targetGoLive === '' ? null : Carbon::parse($this->targetGoLive),
                assignedSuccessManager: $this->managerId,
            ));
        } catch (InvalidArgumentException $exception) {
            $this->addError('schoolId', $exception->getMessage());

            return;
        }

        app(RecordVendorConsoleActionAction::class)->execute($operator, 'onboarding.started', "Onboarding started for {$tenant->name}", $tenant->id, ['checklist_id' => $checklist->id]);

        $this->reset('schoolId', 'targetGoLive', 'managerId');
        $this->toast(__('Onboarding checklist started.'));
    }

    public function completeStep(int $checklistId, string $stepKey): void
    {
        $operator = $this->authorizeVendor();

        $checklist = OnboardingChecklist::query()->withoutGlobalScopes()->findOrFail($checklistId);

        try {
            app(CompleteOnboardingStepAction::class)->execute($checklist->id, $stepKey, $operator->name);
        } catch (SerpException $exception) {
            $this->toast($exception->getMessage(), 'danger');

            return;
        }

        app(RecordVendorConsoleActionAction::class)->execute($operator, 'onboarding.step_completed', "Onboarding step {$stepKey} completed", $checklist->tenant_id, ['checklist_id' => $checklist->id]);

        $this->toast(__('Step completed.'));
    }

    public function alertStalled(): void
    {
        $operator = $this->authorizeVendor();

        $stalled = app(ListStalledOnboardingChecklistsAction::class)->execute();

        app(RecordVendorConsoleActionAction::class)->execute($operator, 'onboarding.stalled_alerted', 'Alerted success managers of stalled checklists', null, ['count' => count($stalled)]);

        $this->toast(__(':count stalled checklist(s) alerted.', ['count' => count($stalled)]));
    }

    public function render(): View
    {
        $this->authorizeVendor();

        $checklists = OnboardingChecklist::query()->withoutGlobalScopes()->orderByDesc('id')->limit(100)->get();
        $thresholdDays = (int) app(SettingResolver::class)->get('saas.onboarding_stall_threshold_days', new ScopeChain);
        $takenSchoolIds = OnboardingChecklist::query()->withoutGlobalScopes()->pluck('school_id');

        return view('saas::vendor.onboarding', [
            'checklists' => $checklists,
            'schoolNames' => School::query()->whereIn('id', $checklists->pluck('school_id'))->pluck('name', 'id'),
            'thresholdDays' => $thresholdDays,
            'tenants' => Tenant::query()->orderBy('name')->limit(500)->get(['id', 'name']),
            'schools' => $this->tenantId === null ? collect() : School::query()->where('tenant_id', $this->tenantId)->whereNotIn('id', $takenSchoolIds)->orderBy('name')->get(['id', 'name']),
            'managers' => User::query()->where('user_type', UserType::Vendor)->orderBy('name')->get(['id', 'name']),
        ]);
    }
}
