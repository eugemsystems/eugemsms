<?php

declare(strict_types=1);

namespace Modules\Compliance\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Compliance\Models\SubjectAccessRequest;
use Modules\Core\Models\School;
use Modules\People\Models\Student;

/**
 * @extends Factory<SubjectAccessRequest>
 */
class SubjectAccessRequestFactory extends Factory
{
    protected $model = SubjectAccessRequest::class;

    public function definition(): array
    {
        return [
            'school_id' => School::factory(),
            'request_type' => 'access',
            'subject_type' => 'student',
            'subject_id' => fn (array $attributes): int => Student::factory()->create(['school_id' => $attributes['school_id']])->id,
            'requester_name' => $this->faker->name(),
            'requester_relationship' => 'guardian',
            'identity_verified' => false,
            'verification_method' => null,
            'verified_by' => null,
            'received_at' => now(),
            'due_by' => now()->addDays(30)->toDateString(),
            'scope_description' => 'All personal data held about the learner.',
            'status' => 'received',
            'refusal_grounds' => null,
            'response_file_id' => null,
            'fulfilled_at' => null,
            'handled_by' => null,
        ];
    }
}
