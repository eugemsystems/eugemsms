<?php

declare(strict_types=1);

namespace Modules\Finance\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\Models\School;
use Modules\Finance\Models\PostingRule;

/**
 * @extends Factory<PostingRule>
 */
class PostingRuleFactory extends Factory
{
    protected $model = PostingRule::class;

    public function definition(): array
    {
        return [
            'school_id' => School::factory(),
            'event_key' => 'fee.tuition.billed',
            'is_active' => true,
        ];
    }
}
