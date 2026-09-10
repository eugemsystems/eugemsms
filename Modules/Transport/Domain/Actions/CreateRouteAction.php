<?php

declare(strict_types=1);

namespace Modules\Transport\Domain\Actions;

use Modules\Core\Domain\Actions\Action;
use Modules\Transport\Domain\DataObjects\CreateRouteData;
use Modules\Transport\Models\Route;
use Modules\Transport\Models\RouteStop;

/**
 * ACT-CreateRoute (Book H2 OPS-01 §2/BR-OPS-01-007).
 */
final class CreateRouteAction extends Action
{
    public function execute(CreateRouteData $data): Route
    {
        return $this->transaction(function () use ($data): Route {
            $route = Route::create([
                'school_id' => $data->schoolId,
                'academic_year_id' => $data->academicYearId,
                'code' => $data->code,
                'name' => $data->name,
                'direction' => $data->direction,
                'assigned_vehicle_id' => $data->assignedVehicleId,
                'assigned_driver_id' => $data->assignedDriverId,
                'assistant_staff_id' => $data->assistantStaffId,
                'capacity' => $data->capacity,
                'current_passengers' => 0,
                'cost_centre_id' => $data->costCentreId,
                'is_active' => true,
            ]);

            foreach ($data->stops as $sequence => $stop) {
                RouteStop::create([
                    'school_id' => $data->schoolId,
                    'route_id' => $route->id,
                    'sequence' => $sequence + 1,
                    'name' => $stop['name'],
                    'landmark' => $stop['landmark'] ?? null,
                    'zone_id' => $stop['zoneId'] ?? null,
                    'distance_from_school_km' => $stop['distanceFromSchoolKm'] ?? null,
                ]);
            }

            return $route;
        });
    }
}
