<?php

declare(strict_types=1);

namespace Modules\Core\Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\Domain\Support\RolloverStatus;
use Modules\Core\Models\PeriodRollover;
use Modules\Core\Models\School;
use Modules\Core\Models\Term;

/**
 * @extends Factory<PeriodRollover>
 */
class PeriodRolloverFactory extends Factory
{
    protected $model = PeriodRollover::class;

    public function definition(): array
    {
        return [
            'school_id' => School::factory(),
            'from_term_id' => Term::factory(),
            'to_term_id' => Term::factory(),
            'status' => RolloverStatus::Pending,
            'initiated_by' => User::factory(),
        ];
    }
}
