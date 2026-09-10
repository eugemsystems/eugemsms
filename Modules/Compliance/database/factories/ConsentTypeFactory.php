<?php

declare(strict_types=1);

namespace Modules\Compliance\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Compliance\Models\ConsentType;
use Modules\Core\Models\School;

/**
 * @extends Factory<ConsentType>
 */
class ConsentTypeFactory extends Factory
{
    protected $model = ConsentType::class;

    public function definition(): array
    {
        return [
            'school_id' => School::factory(),
            'code' => 'photography',
            'name' => 'Photography & Publication',
            'description' => 'Consent to photograph the learner and use images in school publications.',
            'lawful_basis' => 'consent',
            'is_withdrawable' => true,
            'required_for_enrolment' => false,
            'applies_to' => 'student',
            'renewal_frequency_months' => null,
            'is_active' => true,
        ];
    }
}
