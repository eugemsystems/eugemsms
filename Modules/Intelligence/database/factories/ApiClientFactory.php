<?php

declare(strict_types=1);

namespace Modules\Intelligence\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Modules\Core\Models\School;
use Modules\Intelligence\Models\ApiClient;

/**
 * @extends Factory<ApiClient>
 */
class ApiClientFactory extends Factory
{
    protected $model = ApiClient::class;

    public function definition(): array
    {
        return [
            'school_id' => School::factory(),
            'name' => 'Test Integration',
            'client_type' => 'integration',
            'contact_email' => 'integration@example.test',
            'api_key_hash' => Hash::make('test-plaintext-key'),
            'scoped_abilities' => [],
            'rate_limit_per_minute' => 60,
            'ip_allowlist' => null,
            'is_active' => true,
        ];
    }
}
