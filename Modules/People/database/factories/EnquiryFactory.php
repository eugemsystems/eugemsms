<?php

declare(strict_types=1);

namespace Modules\People\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\Models\School;
use Modules\People\Models\Enquiry;

/**
 * @extends Factory<Enquiry>
 */
class EnquiryFactory extends Factory
{
    protected $model = Enquiry::class;

    public function definition(): array
    {
        $school = School::factory();

        return [
            'school_id' => $school,
            'source' => 'walk_in', 'enquirer_name' => 'Mrs Ncube', 'stage' => 'new',
        ];
    }
}
