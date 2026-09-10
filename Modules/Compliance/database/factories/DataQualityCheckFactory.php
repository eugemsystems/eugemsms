<?php

declare(strict_types=1);

namespace Modules\Compliance\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Compliance\Models\DataQualityCheck;
use Modules\Core\Models\School;

/**
 * @extends Factory<DataQualityCheck>
 */
class DataQualityCheckFactory extends Factory
{
    protected $model = DataQualityCheck::class;

    public function definition(): array
    {
        return [
            'school_id' => School::factory(),
            'check_key' => 'missing_national_reg',
            'entity_type' => 'student',
            'affected_count' => 0,
            'affected_ids' => null,
            'severity' => 'error',
            'last_checked_at' => now(),
        ];
    }
}
