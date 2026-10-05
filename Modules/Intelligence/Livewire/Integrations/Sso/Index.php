<?php

declare(strict_types=1);

namespace Modules\Intelligence\Livewire\Integrations\Sso;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use InvalidArgumentException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\School;
use Modules\Intelligence\Domain\Actions\SaveSsoProvisioningConfigAction;
use Modules\Intelligence\Models\SsoProvisioningConfig;

/**
 * `Intelligence\Integrations\Sso\Index` (Book J INT-04 §5,
 * `integration.sso.manage` ⚠⚠). Per-provider domain, credentials and
 * the auto-provision switch. Credentials are write-only: the screen
 * never loads them back, and leaving the box blank keeps what is stored.
 * Staff accounts SSO later provisions still go through CORE-05's
 * identity and role machinery (BR-INT-04-006) — nothing here grants a
 * role.
 */
#[Title('SSO configuration')]
#[Layout('layouts.app')]
final class Index extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;
    use Toasts;

    public string $provider = 'google_workspace';

    public string $domain = '';

    public string $credentials = '';

    public bool $autoProvision = false;

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('integration.sso.manage');
    }

    public function updatedProvider(): void
    {
        $this->load();
    }

    public function save(): void
    {
        $this->authorizePermission('integration.sso.manage');
        $this->resetErrorBag();

        try {
            app(SaveSsoProvisioningConfigAction::class)->execute($this->school->id, $this->provider, $this->domain, $this->credentials, $this->autoProvision);
        } catch (InvalidArgumentException $exception) {
            $this->addError('domain', $exception->getMessage());

            return;
        }

        $this->credentials = '';
        $this->toast(__('SSO configuration saved.'));
    }

    private function load(): void
    {
        $config = SsoProvisioningConfig::where('school_id', $this->school->id)->where('provider', $this->provider)->first();

        $this->domain = $config === null ? '' : $config->domain;
        $this->autoProvision = $config !== null && $config->auto_provision_staff;
        $this->credentials = '';
    }

    public function render(): View
    {
        return view('intelligence::integrations.sso', [
            'providers' => SaveSsoProvisioningConfigAction::PROVIDERS,
            'configs' => SsoProvisioningConfig::where('school_id', $this->school->id)->get(['id', 'provider', 'domain', 'auto_provision_staff', 'sync_status', 'last_synced_at'])->keyBy('provider'),
        ]);
    }
}
