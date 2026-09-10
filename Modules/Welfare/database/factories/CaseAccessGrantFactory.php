<?php

declare(strict_types=1);

namespace Modules\Welfare\Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\Models\School;
use Modules\Welfare\Models\CaseAccessGrant;
use Modules\Welfare\Models\SafeguardingCase;

/**
 * @extends Factory<CaseAccessGrant>
 */
class CaseAccessGrantFactory extends Factory
{
    protected $model = CaseAccessGrant::class;

    public function definition(): array
    {
        $school = School::factory();

        return [
            'school_id' => $school,
            'case_id' => SafeguardingCase::factory()->create(['school_id' => $school]),
            'user_id' => User::factory(),
            'access_level' => 'read',
            'granted_by' => User::factory(),
            'granted_at' => now(),
            'reason' => 'Housemaster needs context for pastoral support.',
            'expires_at' => now()->addDays(30),
        ];
    }
}
