<?php

declare(strict_types=1);

namespace Modules\Academic\Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Academic\Models\ContentDownloadCache;
use Modules\Academic\Models\ContentItem;

/**
 * @extends Factory<ContentDownloadCache>
 */
class ContentDownloadCacheFactory extends Factory
{
    protected $model = ContentDownloadCache::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'content_item_id' => ContentItem::factory(),
            'device_id' => $this->faker->uuid(),
        ];
    }
}
