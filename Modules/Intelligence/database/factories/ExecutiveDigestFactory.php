<?php

declare(strict_types=1);

namespace Modules\Intelligence\Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\Models\School;
use Modules\Intelligence\Models\ExecutiveDigest;

/**
 * @extends Factory<ExecutiveDigest>
 */
class ExecutiveDigestFactory extends Factory
{
    protected $model = ExecutiveDigest::class;

    public function definition(): array
    {
        return [
            'school_id' => School::factory(),
            'recipient_user_id' => User::factory(),
            'digest_date' => now()->toDateString(),
            'content_summary' => ['all_green' => true, 'exceptions' => []],
            'delivered_via' => 'email',
            'sent_at' => now(),
        ];
    }
}
