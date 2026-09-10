<?php

declare(strict_types=1);

namespace Modules\Core\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\Models\School;
use Modules\Core\Models\SchoolSection;

/**
 * @extends Factory<SchoolSection>
 */
class SchoolSectionFactory extends Factory
{
    protected $model = SchoolSection::class;

    public function definition(): array
    {
        return [
            'school_id' => School::factory(),
            'code' => 'JUN',
            'name' => 'Junior School',
            'type' => 'primary',
            'sort_order' => 0,
            'is_active' => true,
        ];
    }
}
