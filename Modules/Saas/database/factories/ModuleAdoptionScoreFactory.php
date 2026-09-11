<?php

declare(strict_types=1);

namespace Modules\Saas\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;
use Modules\Core\Models\School;
use Modules\Saas\Models\ModuleAdoptionScore;

/**
 * @extends Factory<ModuleAdoptionScore>
 */
class ModuleAdoptionScoreFactory extends Factory
{
    protected $model = ModuleAdoptionScore::class;

    public function definition(): array
    {
        return [
            'school_id' => School::factory(),
            'module_code' => 'FIN',
            'period_month' => Carbon::today()->format('Y-m'),
            'activity_signal' => 'journal_posted',
            'activity_count' => 0,
            'is_actively_used' => false,
        ];
    }
}
