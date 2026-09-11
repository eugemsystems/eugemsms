<?php

declare(strict_types=1);

namespace Modules\Saas\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Modules\Saas\Database\Factories\KnowledgeBaseArticleFactory;

/**
 * Book J SAA-03 §2 — public/in-app reference content, not tenant data.
 *
 * @property int $id
 * @property string $slug
 * @property string $title
 * @property string|null $module_code
 * @property string $content
 * @property int $view_count
 * @property int $helpful_votes
 * @property Carbon|null $last_reviewed_on
 */
class KnowledgeBaseArticle extends Model
{
    /** @use HasFactory<KnowledgeBaseArticleFactory> */
    use HasFactory;

    protected $fillable = [
        'slug', 'title', 'module_code', 'content', 'view_count', 'helpful_votes', 'last_reviewed_on',
    ];

    protected function casts(): array
    {
        return [
            'view_count' => 'integer',
            'helpful_votes' => 'integer',
            'last_reviewed_on' => 'date',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return KnowledgeBaseArticleFactory::new();
    }
}
