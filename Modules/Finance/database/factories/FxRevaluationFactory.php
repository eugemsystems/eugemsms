<?php

declare(strict_types=1);

namespace Modules\Finance\Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\Models\School;
use Modules\Core\Models\Term;
use Modules\Finance\Models\FxRevaluation;

/**
 * @extends Factory<FxRevaluation>
 */
class FxRevaluationFactory extends Factory
{
    protected $model = FxRevaluation::class;

    public function definition(): array
    {
        return [
            'school_id' => School::factory(),
            'term_id' => Term::factory(),
            'revaluation_date' => now()->toDateString(),
            'accounts_revalued' => [],
            'gain_minor' => 0,
            'loss_minor' => 0,
            'base_currency' => 'USD',
            'status' => 'posted',
            'performed_by' => User::factory(),
            'performed_at' => now(),
        ];
    }
}
