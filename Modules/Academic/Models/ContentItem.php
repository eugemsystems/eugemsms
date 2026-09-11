<?php

declare(strict_types=1);

namespace Modules\Academic\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\Academic\Database\Factories\ContentItemFactory;
use Modules\Core\Domain\Concerns\BelongsToSchool;
use Modules\Core\Domain\Concerns\HasUlid;
use Modules\Core\Models\File;

/**
 * Book K ACA-08 §2/§3/BR-ACA-08-003 ⭐ — `is_downloadable_offline`
 * is the data-cost-aware delivery flag; see the owning migration.
 *
 * @property int $id
 * @property string $ulid
 * @property int $school_id
 * @property int $course_space_id
 * @property string $content_type
 * @property string $title
 * @property int|null $file_id
 * @property string|null $external_url
 * @property int|null $file_size_bytes
 * @property bool $is_downloadable_offline
 * @property Carbon|null $published_at
 * @property int $view_count
 * @property int|null $sort_order
 */
class ContentItem extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<ContentItemFactory> */
    use HasFactory;

    use HasUlid;

    protected $fillable = [
        'school_id', 'course_space_id', 'content_type', 'title', 'file_id', 'external_url',
        'file_size_bytes', 'is_downloadable_offline', 'published_at', 'view_count', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_downloadable_offline' => 'boolean',
            'published_at' => 'datetime',
            'view_count' => 'integer',
            'file_size_bytes' => 'integer',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return ContentItemFactory::new();
    }

    /**
     * @return BelongsTo<CourseSpace, $this>
     */
    public function courseSpace(): BelongsTo
    {
        return $this->belongsTo(CourseSpace::class);
    }

    /**
     * @return BelongsTo<File, $this>
     */
    public function file(): BelongsTo
    {
        return $this->belongsTo(File::class);
    }

    public function isPublished(): bool
    {
        return $this->published_at !== null;
    }
}
