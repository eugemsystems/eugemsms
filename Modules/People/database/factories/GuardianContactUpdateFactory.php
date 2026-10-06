<?php

declare(strict_types=1);

namespace Modules\People\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\Models\School;
use Modules\People\Models\Guardian;
use Modules\People\Models\GuardianContactUpdate;

/**
 * @extends Factory<GuardianContactUpdate>
 */
class GuardianContactUpdateFactory extends Factory
{
    protected $model = GuardianContactUpdate::class;

    public function definition(): array
    {
        $school = School::factory();

        return [
            'school_id' => $school,
            'guardian_id' => Guardian::factory()->for($school), 'changes' => ['email' => 'new@example.com'], 'status' => 'pending',
        ];
    }
}
