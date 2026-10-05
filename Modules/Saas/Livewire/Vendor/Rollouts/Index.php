<?php

declare(strict_types=1);

namespace Modules\Saas\Livewire\Vendor\Rollouts;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use InvalidArgumentException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Core\Domain\Exceptions\DomainException;
use Modules\Core\Models\FeatureFlag;
use Modules\Core\Models\Tenant;
use Modules\Saas\Domain\Actions\AdvanceFeatureRolloutStageAction;
use Modules\Saas\Domain\Actions\CreateFeatureRolloutAction;
use Modules\Saas\Domain\Actions\RecordVendorConsoleActionAction;
use Modules\Saas\Domain\DataObjects\AdvanceFeatureRolloutStageData;
use Modules\Saas\Domain\DataObjects\CreateFeatureRolloutData;
use Modules\Saas\Livewire\Concerns\AuthorizesVendorConsole;
use Modules\Saas\Models\FeatureRollout;

/**
 * `Saas\Rollouts\Index` (Book J SAA-02 §4, vendor console). Operates
 * CORE-04's own feature flags across tenants. A rollout starts as a pilot
 * on named tenants and advances exactly one stage at a time — pilot →
 * cohort → percentage → general — only when an operator asks for it
 * (BR-SAA-02-004); nothing escalates by itself.
 */
#[Title('Feature rollouts')]
#[Layout('saas::layouts.vendor')]
final class Index extends Component
{
    use AuthorizesVendorConsole;
    use Toasts;

    public string $flagKey = '';

    /** @var array<int, int> */
    public array $pilotTenantIds = [];

    public string $notes = '';

    public ?int $advancingId = null;

    /** @var array<int, int> */
    public array $cohortTenantIds = [];

    public ?int $percentage = null;

    public function mount(): void
    {
        $this->authorizeVendor();
    }

    public function start(): void
    {
        $operator = $this->authorizeVendor();
        $this->resetErrorBag();

        $this->validate(['flagKey' => ['required', 'string'], 'pilotTenantIds' => ['required', 'array', 'min:1'], 'notes' => ['nullable', 'string', 'max:500']]);

        try {
            $rollout = app(CreateFeatureRolloutAction::class)->execute(new CreateFeatureRolloutData(
                featureFlagKey: $this->flagKey, pilotTenantIds: array_map('intval', $this->pilotTenantIds),
                startedBy: $operator->id, notes: $this->notes === '' ? null : $this->notes,
            ));
        } catch (InvalidArgumentException $exception) {
            $this->addError('flagKey', $exception->getMessage());

            return;
        }

        app(RecordVendorConsoleActionAction::class)->execute($operator, 'rollout.started', "Started a pilot of {$rollout->feature_flag_key}", null, ['rollout_id' => $rollout->id, 'pilot_tenant_ids' => $rollout->pilot_tenant_ids]);

        $this->reset('flagKey', 'pilotTenantIds', 'notes');
        $this->toast(__('Pilot started on the chosen tenants only.'));
    }

    public function beginAdvance(int $rolloutId): void
    {
        $this->authorizeVendor();

        $this->advancingId = FeatureRollout::query()->where('rollout_stage', '!=', 'general')->findOrFail($rolloutId)->id;
        $this->cohortTenantIds = [];
        $this->percentage = null;
        $this->resetErrorBag();
    }

    public function advance(): void
    {
        $operator = $this->authorizeVendor();
        $this->resetErrorBag();

        $rollout = FeatureRollout::query()->findOrFail($this->advancingId);

        try {
            $updated = app(AdvanceFeatureRolloutStageAction::class)->execute(new AdvanceFeatureRolloutStageData(
                rolloutId: $rollout->id, cohortTenantIds: array_map('intval', $this->cohortTenantIds), percentage: $this->percentage,
            ));
        } catch (InvalidArgumentException|DomainException $exception) {
            $this->addError('advancingId', $exception->getMessage());

            return;
        }

        app(RecordVendorConsoleActionAction::class)->execute($operator, 'rollout.advanced', "Advanced {$rollout->feature_flag_key} from {$rollout->rollout_stage} to {$updated->rollout_stage}", null, ['rollout_id' => $rollout->id, 'to' => $updated->rollout_stage, 'tenant_ids' => $this->cohortTenantIds, 'percentage' => $this->percentage]);

        $this->advancingId = null;
        $this->toast(__('Advanced to :stage.', ['stage' => $updated->rollout_stage]));
    }

    public function render(): View
    {
        $this->authorizeVendor();

        return view('saas::vendor.rollouts', [
            'rollouts' => FeatureRollout::query()->orderByDesc('id')->limit(100)->get(),
            'flags' => FeatureFlag::query()->where('is_globally_enabled', false)->orderBy('key')->get(['key', 'name']),
            'tenants' => Tenant::query()->orderBy('name')->limit(500)->get(['id', 'name']),
            'stageOrder' => FeatureRollout::STAGE_ORDER,
        ]);
    }
}
