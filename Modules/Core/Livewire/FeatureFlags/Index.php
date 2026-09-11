<?php

declare(strict_types=1);

namespace Modules\Core\Livewire\FeatureFlags;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Core\Domain\Actions\Settings\ToggleFeatureFlagAction;
use Modules\Core\Domain\DataObjects\Settings\ToggleFeatureFlagData;
use Modules\Core\Domain\Exceptions\DomainException;
use Modules\Core\Models\FeatureFlag;

/**
 * `Core\FeatureFlags\Index` (Book A CORE-04 §5). Platform-wide — not
 * per-school — so unlike every other CORE-04 screen this one takes no
 * `{school}`. The spec marks its permission "(vendor)"; CORE-05 hasn't
 * shipped a permission system to gate that yet, so it's reachable by
 * any authenticated user for now, same gap as elsewhere in this build.
 */
#[Title('Feature flags')]
#[Layout('layouts.app')]
final class Index extends Component
{
    use Toasts;

    public bool $showOverrideModal = false;

    public ?int $overrideFlagId = null;

    public string $overrideScopeType = 'school';

    public string $overrideScopeId = '';

    public bool $overrideEnabled = true;

    public function toggleGlobal(string $key, bool $enabled): void
    {
        $this->apply($key, $enabled, null, null);
    }

    public function openOverrideModal(int $flagId): void
    {
        $this->overrideFlagId = $flagId;
        $this->showOverrideModal = true;
    }

    public function saveOverride(): void
    {
        if ($this->overrideFlagId === null || $this->overrideScopeId === '') {
            $this->addError('overrideScopeId', __('Choose a scope id.'));

            return;
        }

        $flag = FeatureFlag::find($this->overrideFlagId);

        if ($flag === null) {
            return;
        }

        $this->apply($flag->key, $this->overrideEnabled, $this->overrideScopeType, (int) $this->overrideScopeId);

        $this->reset(['showOverrideModal', 'overrideFlagId', 'overrideScopeId']);
    }

    private function apply(string $key, bool $enabled, ?string $scopeType, ?int $scopeId): void
    {
        try {
            app(ToggleFeatureFlagAction::class)->execute(new ToggleFeatureFlagData(
                key: $key,
                isEnabled: $enabled,
                scopeType: $scopeType,
                scopeId: $scopeId,
            ));
        } catch (DomainException $e) {
            $this->toast($e->getMessage(), 'danger');

            return;
        }

        $this->toast(__('Feature flag updated.'));
    }

    public function render(): View
    {
        return view('core::feature-flags.index', [
            'flags' => FeatureFlag::with('overrides')->orderBy('key')->get(),
        ]);
    }
}
