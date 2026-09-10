<?php

declare(strict_types=1);

namespace Modules\People\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\Models\File;
use Modules\Core\Models\School;
use Modules\People\Models\Staff;
use Modules\People\Models\StaffDocument;

/**
 * @extends Factory<StaffDocument>
 */
class StaffDocumentFactory extends Factory
{
    protected $model = StaffDocument::class;

    public function definition(): array
    {
        $school = School::factory();

        return [
            'school_id' => $school,
            'staff_id' => Staff::factory()->for($school),
            'document_type' => 'police_clearance',
            'file_id' => File::factory()->state(['school_id' => $school]),
            'is_verified' => false,
        ];
    }
}
