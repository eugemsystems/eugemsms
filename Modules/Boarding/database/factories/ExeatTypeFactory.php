<?php

declare(strict_types=1);

namespace Modules\Boarding\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Boarding\Models\ExeatType;
use Modules\Core\Models\School;

/**
 * @extends Factory<ExeatType>
 */
class ExeatTypeFactory extends Factory
{
    protected $model = ExeatType::class;

    public function definition(): array
    {
        return [
            'school_id' => School::factory(),
            'code' => 'WEEKEND',
            'name' => 'Weekend Exeat',
            'requires_guardian_request' => true,
            'requires_document' => false,
            'min_notice_hours' => 24,
            'allowed_per_term' => 3,
            'counts_toward_quota' => true,
            'blocks_on_fee_arrears' => false,
            'blocks_on_suspension' => true,
            'is_active' => true,
        ];
    }
}
