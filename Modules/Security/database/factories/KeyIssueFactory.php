<?php

declare(strict_types=1);

namespace Modules\Security\Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\Models\School;
use Modules\Security\Models\KeyAndCard;
use Modules\Security\Models\KeyIssue;

/**
 * @extends Factory<KeyIssue>
 */
class KeyIssueFactory extends Factory
{
    protected $model = KeyIssue::class;

    public function definition(): array
    {
        return [
            'school_id' => School::factory(),
            'key_id' => fn (array $attributes): int => KeyAndCard::factory()->create(['school_id' => $attributes['school_id']])->id,
            'issued_at' => now(),
            'issued_by' => User::factory(),
            'status' => 'issued',
        ];
    }
}
