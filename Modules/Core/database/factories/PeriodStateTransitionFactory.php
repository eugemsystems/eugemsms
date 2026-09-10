<?php

declare(strict_types=1);

namespace Modules\Core\Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\Domain\Support\PeriodState;
use Modules\Core\Domain\Support\PeriodType;
use Modules\Core\Models\PeriodStateTransition;
use Modules\Core\Models\School;
use Modules\Core\Models\Term;

/**
 * @extends Factory<PeriodStateTransition>
 */
class PeriodStateTransitionFactory extends Factory
{
    protected $model = PeriodStateTransition::class;

    public function definition(): array
    {
        return [
            'school_id' => School::factory(),
            'term_id' => Term::factory(),
            'period_type' => PeriodType::Academic,
            'from_state' => PeriodState::Planned,
            'to_state' => PeriodState::Open,
            'performed_by' => User::factory(),
            'occurred_at' => now(),
        ];
    }
}
