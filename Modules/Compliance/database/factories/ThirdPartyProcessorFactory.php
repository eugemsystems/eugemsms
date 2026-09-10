<?php

declare(strict_types=1);

namespace Modules\Compliance\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Compliance\Models\ThirdPartyProcessor;
use Modules\Core\Models\School;

/**
 * @extends Factory<ThirdPartyProcessor>
 */
class ThirdPartyProcessorFactory extends Factory
{
    protected $model = ThirdPartyProcessor::class;

    public function definition(): array
    {
        return [
            'school_id' => School::factory(),
            'name' => 'Example SMS Gateway',
            'processor_type' => 'sms',
            'data_shared' => ['guardian_phone_number'],
            'purpose' => 'Sending fee and attendance notifications to guardians.',
            'country' => 'ZW',
            'agreement_file_id' => null,
            'agreement_expires_on' => null,
            'status' => 'active',
            'last_reviewed_on' => now()->toDateString(),
        ];
    }
}
