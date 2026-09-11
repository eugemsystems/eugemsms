<?php

declare(strict_types=1);

namespace Modules\Academic\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Academic\Models\ContentItem;
use Modules\Academic\Models\CourseSpace;

/**
 * @extends Factory<ContentItem>
 */
class ContentItemFactory extends Factory
{
    protected $model = ContentItem::class;

    public function definition(): array
    {
        $courseSpace = CourseSpace::factory()->create();

        return [
            'school_id' => $courseSpace->school_id,
            'course_space_id' => $courseSpace->id,
            'content_type' => 'note',
            'title' => $this->faker->sentence(3),
            'file_size_bytes' => 340_000,
            'is_downloadable_offline' => true,
            'view_count' => 0,
        ];
    }
}
