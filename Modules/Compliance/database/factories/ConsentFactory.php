<?php

declare(strict_types=1);

namespace Modules\Compliance\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Compliance\Models\Consent;
use Modules\Compliance\Models\ConsentType;
use Modules\Core\Models\School;
use Modules\People\Models\Guardian;
use Modules\People\Models\Student;

/**
 * @extends Factory<Consent>
 */
class ConsentFactory extends Factory
{
    protected $model = Consent::class;

    public function definition(): array
    {
        return [
            'school_id' => School::factory(),
            'consent_type_id' => fn (array $attributes): int => ConsentType::factory()->create(['school_id' => $attributes['school_id']])->id,
            'subject_type' => 'student',
            'subject_id' => fn (array $attributes): int => Student::factory()->create(['school_id' => $attributes['school_id']])->id,
            'granted_by_type' => 'guardian',
            'granted_by_id' => fn (array $attributes): int => Guardian::factory()->create(['school_id' => $attributes['school_id']])->id,
            'granted' => true,
            'granted_at' => now(),
            'method' => 'portal',
            'notice_version' => 'v1',
            'document_file_id' => null,
            'witness_staff_id' => null,
            'ip_address' => '127.0.0.1',
            'expires_on' => null,
            'withdrawn_at' => null,
            'withdrawn_by' => null,
            'withdrawal_reason' => null,
        ];
    }
}
