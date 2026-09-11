<?php

declare(strict_types=1);

namespace Modules\Saas\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Saas\Models\KnowledgeBaseArticle;

/**
 * @extends Factory<KnowledgeBaseArticle>
 */
class KnowledgeBaseArticleFactory extends Factory
{
    protected $model = KnowledgeBaseArticle::class;

    public function definition(): array
    {
        return [
            'slug' => fake()->unique()->slug(3),
            'title' => fake()->sentence(4),
            'module_code' => null,
            'content' => fake()->paragraphs(3, true),
            'view_count' => 0,
            'helpful_votes' => 0,
            'last_reviewed_on' => null,
        ];
    }
}
