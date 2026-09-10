<?php

declare(strict_types=1);

namespace Modules\Academic\Domain\Actions;

use Modules\Academic\Domain\DataObjects\CreateVenueData;
use Modules\Academic\Models\Venue;
use Modules\Core\Domain\Actions\Action;

final class CreateVenueAction extends Action
{
    public function execute(CreateVenueData $data): Venue
    {
        return $this->transaction(fn (): Venue => Venue::create([
            'school_id' => $data->schoolId,
            'code' => $data->code,
            'name' => $data->name,
            'venue_type' => $data->venueType,
            'capacity' => $data->capacity,
            'exam_capacity' => $data->examCapacity,
            'building' => $data->building,
            'floor' => $data->floor,
            'facilities' => $data->facilities,
            'is_active' => true,
        ]));
    }
}
