<?php

declare(strict_types=1);

namespace Modules\Compliance\Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Compliance\Models\DataBreach;
use Modules\Core\Models\School;

/**
 * @extends Factory<DataBreach>
 */
class DataBreachFactory extends Factory
{
    protected $model = DataBreach::class;

    public function definition(): array
    {
        return [
            'school_id' => School::factory(),
            'detected_at' => now(),
            'occurred_at' => null,
            'breach_type' => 'misdirected_communication',
            'description' => 'A report was sent to the wrong recipient.',
            'data_categories' => ['contact_details'],
            'records_affected' => 1,
            'subjects_affected' => 1,
            'includes_minors' => false,
            'severity' => 'low',
            'containment_actions' => null,
            'contained_at' => null,
            'authority_notified' => false,
            'authority_notified_at' => null,
            'subjects_notified' => false,
            'subjects_notified_at' => null,
            'root_cause' => null,
            'remedial_actions' => null,
            'status' => 'detected',
            'reported_by' => User::factory(),
        ];
    }
}
