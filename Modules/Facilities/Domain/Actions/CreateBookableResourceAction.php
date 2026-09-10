<?php

declare(strict_types=1);

namespace Modules\Facilities\Domain\Actions;

use Modules\Core\Domain\Actions\Action;
use Modules\Facilities\Domain\DataObjects\CreateBookableResourceData;
use Modules\Facilities\Models\BookableResource;

/**
 * ACT-CreateBookableResource (Book H2 OPS-05 §2).
 */
final class CreateBookableResourceAction extends Action
{
    public function execute(CreateBookableResourceData $data): BookableResource
    {
        return $this->transaction(fn (): BookableResource => BookableResource::create([
            'school_id' => $data->schoolId,
            'venue_id' => $data->venueId,
            'vehicle_id' => $data->vehicleId,
            'code' => $data->code,
            'name' => $data->name,
            'resource_type' => $data->resourceType,
            'capacity' => $data->capacity,
            'is_externally_hireable' => $data->isExternallyHireable,
            'hire_rate_minor' => $data->hireRateMinor,
            'hire_rate_unit' => $data->hireRateUnit,
            'hire_currency' => $data->hireCurrency,
            'deposit_minor' => $data->depositMinor,
            'requires_setup_minutes' => $data->requiresSetupMinutes,
            'requires_cleaning_minutes' => $data->requiresCleaningMinutes,
            'booking_lead_time_hours' => $data->bookingLeadTimeHours,
            'cost_centre_id' => $data->costCentreId,
            'income_account_id' => $data->incomeAccountId,
            'is_active' => true,
        ]));
    }
}
