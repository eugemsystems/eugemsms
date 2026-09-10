<?php

declare(strict_types=1);

namespace Modules\Core\Livewire\Install;

use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Modules\Core\Domain\Actions\Install\ProvisionFirstSchoolAction;
use Modules\Core\Domain\Actions\Install\ProvisionFirstTenantAction;
use Modules\Core\Domain\DataObjects\Install\SchoolProvisionData;
use Modules\Core\Domain\DataObjects\Install\TenantProvisionData;
use Modules\Core\Domain\Support\Install\InstallStepKey;
use Modules\Core\Livewire\Install\Concerns\InstallStepComponent;

/**
 * `Install\Organisation` (Book A CORE-01 §5) — "Tenant & school". Tenant
 * name, school name, code, base currency.
 */
#[Title('Tenant & School')]
#[Layout('core::layouts.install', ['step' => InstallStepKey::Organisation])]
final class Organisation extends InstallStepComponent
{
    public string $tenantName = '';

    public string $tenantSlug = '';

    public string $schoolName = '';

    public string $schoolCode = '';

    public string $baseCurrency = 'USD';

    public string $timezone = 'Africa/Harare';

    public string $locale = 'en_ZW';

    protected function stepKey(): InstallStepKey
    {
        return InstallStepKey::Organisation;
    }

    public function continue(): void
    {
        $this->validate([
            'tenantName' => ['required', 'string', 'max:150'],
            'tenantSlug' => ['required', 'string', 'max:60', 'alpha_dash'],
            'schoolName' => ['required', 'string', 'max:200'],
            'schoolCode' => ['required', 'string', 'max:20'],
            'baseCurrency' => ['required', 'string', 'size:3'],
        ]);

        $adminUserId = $this->progress()->payloadOf(InstallStepKey::Administrator)['user_id'] ?? null;

        abort_if($adminUserId === null, 500, 'No administrator account was found for this installation.');

        $tenant = app(ProvisionFirstTenantAction::class)->execute(new TenantProvisionData(
            name: $this->tenantName,
            slug: $this->tenantSlug,
        ));

        $school = app(ProvisionFirstSchoolAction::class)->execute(new SchoolProvisionData(
            tenantId: $tenant->id,
            name: $this->schoolName,
            code: $this->schoolCode,
            baseCurrency: $this->baseCurrency,
            timezone: $this->timezone,
            locale: $this->locale,
            adminUserId: $adminUserId,
        ));

        $this->completeStepAndContinue(['tenant_id' => $tenant->id, 'school_id' => $school->id]);
    }

    public function render(): View
    {
        return view('core::install.organisation');
    }
}
