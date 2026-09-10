<?php

declare(strict_types=1);

namespace Modules\Intelligence\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\Models\School;
use Modules\Intelligence\Models\ApiClient;
use Modules\Intelligence\Models\ApiUsageLog;

/**
 * @extends Factory<ApiUsageLog>
 */
class ApiUsageLogFactory extends Factory
{
    protected $model = ApiUsageLog::class;

    public function definition(): array
    {
        $school = School::factory();

        return [
            'school_id' => $school,
            'client_id' => ApiClient::factory()->for($school),
            'endpoint' => '/api/v1/test',
            'method' => 'GET',
            'status_code' => 200,
            'duration_ms' => 50,
            'occurred_at' => now(),
        ];
    }
}
