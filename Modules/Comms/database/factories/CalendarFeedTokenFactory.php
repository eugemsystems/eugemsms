<?php

declare(strict_types=1);

namespace Modules\Comms\Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Comms\Models\CalendarFeedToken;
use Modules\Core\Models\School;

/**
 * @extends Factory<CalendarFeedToken>
 */
class CalendarFeedTokenFactory extends Factory
{
    protected $model = CalendarFeedToken::class;

    public function definition(): array
    {
        return [
            'school_id' => School::factory(),
            'token' => $this->faker->unique()->sha256(),
            'user_id' => User::factory(),
            'audience_scope' => 'whole_school',
            'audience_scope_id' => null,
            'revoked_at' => null,
        ];
    }
}
