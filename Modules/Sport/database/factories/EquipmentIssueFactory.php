<?php

declare(strict_types=1);

namespace Modules\Sport\Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\Models\School;
use Modules\People\Models\Student;
use Modules\Sport\Models\Activity;
use Modules\Sport\Models\EquipmentIssue;
use Modules\Stores\Models\FixedAsset;

/**
 * @extends Factory<EquipmentIssue>
 */
class EquipmentIssueFactory extends Factory
{
    protected $model = EquipmentIssue::class;

    public function definition(): array
    {
        return [
            'school_id' => School::factory(),
            'activity_id' => fn (array $attributes): int => Activity::factory()->create(['school_id' => $attributes['school_id']])->id,
            'asset_id' => fn (array $attributes): int => FixedAsset::factory()->create(['school_id' => $attributes['school_id']])->id,
            'student_id' => fn (array $attributes): int => Student::factory()->create(['school_id' => $attributes['school_id']])->id,
            'issued_at' => now(),
            'issued_by' => User::factory(),
        ];
    }
}
