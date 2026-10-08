<?php

declare(strict_types=1);

namespace Modules\Academic\Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Academic\Models\LearnerProject;
use Modules\Academic\Models\ProjectPortfolio;
use Modules\Core\Models\School;

/**
 * @extends Factory<ProjectPortfolio>
 */
class ProjectPortfolioFactory extends Factory
{
    protected $model = ProjectPortfolio::class;

    public function definition(): array
    {
        return [
            'school_id' => School::factory(),
            'learner_project_id' => LearnerProject::factory(),
            'compiled_by' => User::factory(),
            'compiled_at' => now(),
        ];
    }
}
