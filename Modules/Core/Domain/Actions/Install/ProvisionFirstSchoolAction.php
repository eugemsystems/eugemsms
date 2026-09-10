<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Actions\Install;

use Illuminate\Support\Facades\Validator;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\DataObjects\Install\SchoolProvisionData;
use Modules\Core\Models\School;

/**
 * ACT-ProvisionFirstSchool (Book A CORE-01 §3). Deliberately its own
 * action rather than a thin wrapper over CORE-02's `CreateSchoolAction`:
 * the installer collects no ZIMSEC `category` (CreateSchoolAction
 * requires one) and has no acting user yet to record as `created_by` in
 * the usual sense — this is provisioning, not an admin's day-two CRUD
 * action. Uses exactly the same `School` model CORE-02 owns, so there is
 * no divergent schema, only a divergent (and smaller) set of inputs.
 * Attaches the installer's super admin as the school's primary user so
 * `SetSchoolContext` resolves immediately after install.
 */
final class ProvisionFirstSchoolAction extends Action
{
    public function execute(SchoolProvisionData $data): School
    {
        Validator::make(
            ['name' => $data->name, 'code' => $data->code, 'base_currency' => $data->baseCurrency],
            [
                'name' => ['required', 'string', 'max:200'],
                'code' => ['required', 'string', 'max:20'],
                'base_currency' => ['required', 'string', 'size:3'],
            ],
        )->validate();

        return $this->transaction(function () use ($data): School {
            $school = School::create([
                'tenant_id' => $data->tenantId,
                'code' => $data->code,
                'name' => $data->name,
                'base_currency' => $data->baseCurrency,
                'timezone' => $data->timezone,
                'locale' => $data->locale,
                'status' => 'active',
            ]);

            $school->users()->attach($data->adminUserId, ['is_primary' => true, 'status' => 'active']);

            return $school;
        });
    }
}
