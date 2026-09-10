<?php

declare(strict_types=1);

namespace Modules\Compliance\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Compliance\Models\StatutoryDocument;
use Modules\Core\Models\School;

/**
 * @extends Factory<StatutoryDocument>
 */
class StatutoryDocumentFactory extends Factory
{
    protected $model = StatutoryDocument::class;

    public function definition(): array
    {
        return [
            'school_id' => School::factory(),
            'document_type' => 'registration_certificate',
            'reference_number' => (string) $this->faker->numerify('REG-#####'),
            'issuing_authority' => 'Ministry of Primary and Secondary Education',
            'issued_on' => now()->subYear()->toDateString(),
            'expires_on' => now()->addYear()->toDateString(),
            'file_id' => null,
            'renewal_lead_days' => 60,
            'responsible_staff_id' => null,
            'status' => 'valid',
        ];
    }
}
