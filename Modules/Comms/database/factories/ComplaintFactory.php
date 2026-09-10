<?php

declare(strict_types=1);

namespace Modules\Comms\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Comms\Models\Complaint;
use Modules\Comms\Models\ComplaintCategory;
use Modules\Core\Models\School;

/**
 * @extends Factory<Complaint>
 */
class ComplaintFactory extends Factory
{
    protected $model = Complaint::class;

    public function definition(): array
    {
        $school = School::factory();

        return [
            'school_id' => $school,
            'complaint_number' => 'CMP/'.$this->faker->unique()->numerify('######'),
            'category_id' => ComplaintCategory::factory()->for($school),
            'raised_by_type' => 'guardian',
            'raised_by_id' => null,
            'subject' => 'Late bus pickup',
            'description' => 'The bus was over an hour late again.',
            'related_student_id' => null,
            'severity' => 'medium',
            'assigned_to_staff_id' => null,
            'sla_due_at' => now()->addHours(72),
            'status' => 'received',
        ];
    }
}
