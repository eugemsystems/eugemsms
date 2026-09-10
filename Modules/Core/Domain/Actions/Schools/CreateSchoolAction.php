<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Actions\Schools;

use Illuminate\Support\Facades\Validator;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\DataObjects\Schools\CreateSchoolData;
use Modules\Core\Domain\Events\Schools\SchoolCreated;
use Modules\Core\Models\School;

/**
 * ACT-CreateSchool (Book A CORE-02 §3). BR-CORE-02-001: `code` is unique
 * within the tenant, uppercase alphanumeric, 2-20 characters — and
 * immutable from here on (see UpdateSchoolProfileAction, which never
 * accepts it). BR-CORE-02-011: `centre_number`, where given, is unique
 * across the entire system, not just the tenant.
 */
final class CreateSchoolAction extends Action
{
    public function execute(CreateSchoolData $data): School
    {
        Validator::make(
            [
                'tenant_id' => $data->tenantId,
                'code' => $data->code,
                'name' => $data->name,
                'category' => $data->category,
                'centre_number' => $data->centreNumber,
                'base_currency' => $data->baseCurrency,
            ],
            [
                'tenant_id' => ['required', 'integer', 'exists:tenants,id'],
                'code' => [
                    'required', 'string', 'min:2', 'max:20', 'regex:/^[A-Z0-9]+$/',
                    'unique:schools,code,NULL,id,tenant_id,'.$data->tenantId,
                ],
                'name' => ['required', 'string', 'max:200'],
                'category' => ['required', 'in:government,council,mission,trust,private'],
                'centre_number' => ['nullable', 'string', 'max:20', 'unique:schools,centre_number'],
                'base_currency' => ['required', 'string', 'size:3'],
            ],
        )->validate();

        return $this->transaction(function () use ($data): School {
            $school = School::create([
                'tenant_id' => $data->tenantId,
                'code' => $data->code,
                'name' => $data->name,
                'short_name' => $data->shortName,
                'centre_number' => $data->centreNumber,
                'emis_code' => $data->emisCode,
                'category' => $data->category,
                'responsible_authority' => $data->responsibleAuthority,
                'band' => $data->band,
                'province' => $data->province,
                'district' => $data->district,
                'base_currency' => $data->baseCurrency,
                'timezone' => $data->timezone,
                'locale' => $data->locale,
                // Set explicitly rather than left to the schools table's
                // own column default: create()'s returned in-memory model
                // reflects only what's given here, so a caller reading
                // $school->primary_colour right after this call (without
                // a fresh SELECT) would otherwise see null.
                'primary_colour' => '#1a3a5c',
                'status' => 'active',
                'created_by' => $data->actingUserId,
                'updated_by' => $data->actingUserId,
            ]);

            event(new SchoolCreated($school));

            return $school;
        });
    }
}
