<?php

declare(strict_types=1);

namespace Modules\Saas\Livewire\Vendor\Releases;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use InvalidArgumentException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Core\Domain\Exceptions\DomainException;
use Modules\Core\Models\Tenant;
use Modules\Saas\Domain\Actions\ConfirmReleaseStableAction;
use Modules\Saas\Domain\Actions\RecordVendorConsoleActionAction;
use Modules\Saas\Domain\Actions\RollbackReleaseAction;
use Modules\Saas\Domain\Actions\StartCanaryReleaseAction;
use Modules\Saas\Domain\DataObjects\StartCanaryReleaseData;
use Modules\Saas\Livewire\Concerns\AuthorizesVendorConsole;
use Modules\Saas\Models\ReleaseDeployment;

/**
 * `Saas\Releases\Index` (Book J SAA-02 §4, vendor console). A release is
 * deployed as a canary to named tenants first, and can be rolled back until
 * an operator explicitly confirms it stable (BR-SAA-02-005) — after which
 * rollback is gone. The buttons offered follow the same rule the Actions
 * enforce.
 */
#[Title('Releases')]
#[Layout('saas::layouts.vendor')]
final class Index extends Component
{
    use AuthorizesVendorConsole;
    use Toasts;

    public string $version = '';

    /** @var array<int, int> */
    public array $canaryTenantIds = [];

    public function mount(): void
    {
        $this->authorizeVendor();
    }

    public function startCanary(): void
    {
        $operator = $this->authorizeVendor();
        $this->resetErrorBag();

        $this->validate(['version' => ['required', 'string', 'max:20'], 'canaryTenantIds' => ['required', 'array', 'min:1']]);

        try {
            $release = app(StartCanaryReleaseAction::class)->execute(new StartCanaryReleaseData($this->version, array_map('intval', $this->canaryTenantIds)));
        } catch (InvalidArgumentException $exception) {
            $this->addError('version', $exception->getMessage());

            return;
        }

        app(RecordVendorConsoleActionAction::class)->execute($operator, 'release.canary_started', "Canary of {$release->version} started", null, ['release_id' => $release->id, 'canary_tenant_ids' => $release->canary_tenant_ids]);

        $this->reset('version', 'canaryTenantIds');
        $this->toast(__('Canary started; rollback stays available until you confirm it stable.'));
    }

    public function confirmStable(int $releaseId): void
    {
        $operator = $this->authorizeVendor();

        $release = ReleaseDeployment::query()->findOrFail($releaseId);

        try {
            app(ConfirmReleaseStableAction::class)->execute($release->id);
        } catch (InvalidArgumentException $exception) {
            $this->toast($exception->getMessage(), 'danger');

            return;
        }

        app(RecordVendorConsoleActionAction::class)->execute($operator, 'release.confirmed_stable', "Release {$release->version} confirmed stable — rollback closed", null, ['release_id' => $release->id]);

        $this->toast(__('Confirmed stable. Rollback is no longer available.'));
    }

    public function rollback(int $releaseId): void
    {
        $operator = $this->authorizeVendor();

        $release = ReleaseDeployment::query()->findOrFail($releaseId);

        try {
            app(RollbackReleaseAction::class)->execute($release->id);
        } catch (DomainException $exception) {
            $this->toast($exception->getMessage(), 'danger');

            return;
        }

        app(RecordVendorConsoleActionAction::class)->execute($operator, 'release.rolled_back', "Release {$release->version} rolled back", null, ['release_id' => $release->id]);

        $this->toast(__('Rolled back.'));
    }

    public function render(): View
    {
        $this->authorizeVendor();

        $releases = ReleaseDeployment::query()->orderByDesc('id')->limit(100)->get();

        return view('saas::vendor.releases', [
            'releases' => $releases,
            'tenants' => Tenant::query()->orderBy('name')->limit(500)->get(['id', 'name']),
            'tenantNames' => Tenant::query()->whereIn('id', $releases->pluck('canary_tenant_ids')->flatten()->filter()->unique())->pluck('name', 'id'),
        ]);
    }
}
