<?php

declare(strict_types=1);

namespace Modules\Academic\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Academic\Models\AttendanceReasonCode;
use Modules\Core\Models\School;

/**
 * @extends Factory<AttendanceReasonCode>
 */
class AttendanceReasonCodeFactory extends Factory
{
    protected $model = AttendanceReasonCode::class;

    public function definition(): array
    {
        return [
            'school_id' => School::factory(),
            'code' => strtoupper($this->faker->unique()->lexify('??????')),
            'name' => $this->faker->words(2, true),
            'counts_as_present' => false,
            'counts_toward_percentage' => true,
            'is_authorised' => true,
            'requires_document' => false,
            'suppresses_notification' => false,
            'triggers_welfare_flag' => false,
            'is_active' => true,
        ];
    }
}
