<?php

declare(strict_types=1);

namespace Modules\Intelligence\Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\Models\School;
use Modules\Intelligence\Models\CustomReport;
use Modules\Intelligence\Models\ReportShare;

/**
 * @extends Factory<ReportShare>
 */
class ReportShareFactory extends Factory
{
    protected $model = ReportShare::class;

    public function definition(): array
    {
        $school = School::factory();

        return [
            'school_id' => $school,
            'report_id' => CustomReport::factory()->for($school),
            'shared_with_type' => 'user',
            'shared_with_id' => User::factory()->create()->id,
            'can_edit' => false,
            'shared_by' => User::factory(),
        ];
    }
}
