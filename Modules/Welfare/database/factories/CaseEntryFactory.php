<?php

declare(strict_types=1);

namespace Modules\Welfare\Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\Models\School;
use Modules\Welfare\Models\CaseEntry;
use Modules\Welfare\Models\SafeguardingCase;

/**
 * @extends Factory<CaseEntry>
 */
class CaseEntryFactory extends Factory
{
    protected $model = CaseEntry::class;

    public function definition(): array
    {
        $school = School::factory();

        return [
            'school_id' => $school,
            'case_id' => SafeguardingCase::factory()->create(['school_id' => $school]),
            'entry_type' => 'observation',
            'entry_at' => now(),
            'recorded_at' => now(),
            'content' => 'A routine check-in with the learner.',
            'is_learner_account' => false,
            'recorded_by' => User::factory(),
        ];
    }
}
