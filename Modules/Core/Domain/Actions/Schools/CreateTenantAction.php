<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Actions\Schools;

use Illuminate\Support\Facades\Validator;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\DataObjects\Schools\CreateTenantData;
use Modules\Core\Models\Tenant;

/**
 * ACT-CreateTenant (Book A CORE-02 §3).
 */
final class CreateTenantAction extends Action
{
    public function execute(CreateTenantData $data): Tenant
    {
        Validator::make(
            [
                'name' => $data->name,
                'slug' => $data->slug,
                'type' => $data->type,
                'contact_email' => $data->contactEmail,
                'country' => $data->country,
            ],
            [
                'name' => ['required', 'string', 'max:150'],
                'slug' => ['required', 'string', 'max:60', 'alpha_dash', 'unique:tenants,slug'],
                'type' => ['required', 'in:independent,trust,mission,council,group'],
                'contact_email' => ['nullable', 'email', 'max:150'],
                'country' => ['required', 'string', 'size:2'],
            ],
        )->validate();

        return $this->transaction(fn (): Tenant => Tenant::create([
            'name' => $data->name,
            'slug' => $data->slug,
            'type' => $data->type,
            'contact_name' => $data->contactName,
            'contact_email' => $data->contactEmail,
            'contact_phone' => $data->contactPhone,
            'country' => $data->country,
            'status' => 'trial',
            'is_group_reporting_enabled' => $data->isGroupReportingEnabled,
        ]));
    }
}
