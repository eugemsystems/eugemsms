<?php

declare(strict_types=1);

namespace Modules\Transport\Domain\Actions;

use Illuminate\Validation\ValidationException;
use Modules\Core\Domain\Actions\Action;
use Modules\Transport\Domain\DataObjects\AssignLearnerToRouteData;
use Modules\Transport\Domain\Events\LearnerAssignedToRoute;
use Modules\Transport\Domain\Exceptions\RouteCapacityExceededException;
use Modules\Transport\Models\LearnerTransport;
use Modules\Transport\Models\Route;
use Modules\Transport\Models\RouteStop;

/**
 * ACT-AssignLearnerToRoute (Book H2 OPS-01 §4 ⭐/BR-OPS-01-006/007/008/
 * AC-OPS-01-002).
 */
final class AssignLearnerToRouteAction extends Action
{
    public function execute(AssignLearnerToRouteData $data): LearnerTransport
    {
        if (! $data->authorisedByGuardian) {
            throw ValidationException::withMessages([
                'authorisedByGuardian' => 'Guardian authorisation is required before a learner is placed on a route (BR-OPS-01-008).',
            ]);
        }

        $route = Route::findOrFail($data->routeId);
        $pickupStop = RouteStop::findOrFail($data->pickupStopId);

        if ($pickupStop->zone_id === null) {
            throw ValidationException::withMessages([
                'pickupStopId' => 'The pickup stop has no transport zone assigned — a fee cannot be derived from it (BR-OPS-01-006).',
            ]);
        }

        $activeCount = LearnerTransport::where('route_id', $route->id)->where('status', 'active')->count();

        if ($activeCount >= $route->capacity) {
            if (! $data->overrideCapacity) {
                throw RouteCapacityExceededException::forRoute($route->id, $route->capacity);
            }

            if (trim((string) $data->overrideReason) === '') {
                throw ValidationException::withMessages([
                    'overrideReason' => 'A reason is required to exceed route capacity (BR-OPS-01-007).',
                ]);
            }
        }

        return $this->transaction(function () use ($data, $route, $pickupStop): LearnerTransport {
            $existing = LearnerTransport::where('school_id', $data->schoolId)
                ->where('term_id', $data->termId)
                ->where('student_id', $data->studentId)
                ->where('direction', $data->direction)
                ->where('status', 'active')
                ->first();

            if ($existing !== null) {
                $existing->update([
                    'status' => 'ended',
                    'effective_to' => $data->effectiveFrom->copy()->subDay()->toDateString(),
                ]);

                Route::where('id', $existing->route_id)->decrement('current_passengers');
            }

            $assignment = LearnerTransport::create([
                'school_id' => $data->schoolId,
                'academic_year_id' => $data->academicYearId,
                'term_id' => $data->termId,
                'student_id' => $data->studentId,
                'route_id' => $route->id,
                'pickup_stop_id' => $pickupStop->id,
                'dropoff_stop_id' => $data->dropoffStopId,
                'zone_id' => $pickupStop->zone_id,
                'direction' => $data->direction,
                'effective_from' => $data->effectiveFrom->toDateString(),
                'status' => 'active',
                'billing_status' => 'pending',
                'authorised_by_guardian' => true,
                'notes' => $data->notes,
            ]);

            $route->increment('current_passengers');

            event(new LearnerAssignedToRoute($assignment));

            return $assignment;
        });
    }
}
