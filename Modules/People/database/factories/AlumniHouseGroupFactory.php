<?php

declare(strict_types=1);

namespace Modules\People\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\Models\School;
use Modules\People\Models\AlumniHouseGroup;

/**
 * @extends Factory<AlumniHouseGroup>
 */
class AlumniHouseGroupFactory extends Factory
{
    protected $model = AlumniHouseGroup::class;

    public function definition(): array
    {
        return [
            'school_id' => School::factory(),
            'graduation_year' => (int) now()->year,
            'group_name' => 'Class of '.now()->year,
        ];
    }
}
