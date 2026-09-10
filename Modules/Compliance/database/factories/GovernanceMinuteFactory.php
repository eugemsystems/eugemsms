<?php

declare(strict_types=1);

namespace Modules\Compliance\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Compliance\Models\GovernanceMinute;
use Modules\Core\Models\School;

/**
 * @extends Factory<GovernanceMinute>
 */
class GovernanceMinuteFactory extends Factory
{
    protected $model = GovernanceMinute::class;

    public function definition(): array
    {
        return [
            'school_id' => School::factory(),
            'body' => 'board',
            'meeting_date' => now()->toDateString(),
            'attendees' => ['Chairperson', 'Head'],
            'minutes_file_id' => null,
            'resolutions' => ['Approved the annual budget.'],
            'confidentiality' => 'open',
            'access_role_ids' => null,
        ];
    }
}
