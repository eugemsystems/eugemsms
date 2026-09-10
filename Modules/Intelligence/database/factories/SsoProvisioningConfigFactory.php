<?php

declare(strict_types=1);

namespace Modules\Intelligence\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\Models\School;
use Modules\Intelligence\Models\SsoProvisioningConfig;

/**
 * @extends Factory<SsoProvisioningConfig>
 */
class SsoProvisioningConfigFactory extends Factory
{
    protected $model = SsoProvisioningConfig::class;

    public function definition(): array
    {
        return [
            'school_id' => School::factory(),
            'provider' => 'google_workspace',
            'domain' => 'example.test',
            'credentials' => 'test-credentials-blob',
            'auto_provision_staff' => false,
            'sync_status' => 'pending',
        ];
    }
}
