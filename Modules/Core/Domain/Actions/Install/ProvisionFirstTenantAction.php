<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Actions\Install;

use Illuminate\Support\Facades\Validator;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\DataObjects\Install\TenantProvisionData;
use Modules\Core\Models\Tenant;

/**
 * ACT-ProvisionFirstTenant (Book A CORE-01 §3).
 */
final class ProvisionFirstTenantAction extends Action
{
    public function execute(TenantProvisionData $data): Tenant
    {
        Validator::make(
            ['name' => $data->name, 'slug' => $data->slug],
            [
                'name' => ['required', 'string', 'max:150'],
                'slug' => ['required', 'string', 'max:60', 'alpha_dash', 'unique:tenants,slug'],
            ],
        )->validate();

        return $this->transaction(fn (): Tenant => Tenant::create([
            'name' => $data->name,
            'slug' => $data->slug,
            'type' => 'independent',
            'country' => 'ZW',
            'status' => 'active',
            'is_group_reporting_enabled' => false,
        ]));
    }
}
