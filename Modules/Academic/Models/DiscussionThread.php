<?php

declare(strict_types=1);

namespace Modules\Academic\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Academic\Database\Factories\DiscussionThreadFactory;
use Modules\Core\Domain\Concerns\BelongsToSchool;

/**
 * Book K ACA-08 §2.
 *
 * @property int $id
 * @property int $school_id
 * @property int $course_space_id
 * @property string $title
 * @property int $created_by
 * @property bool $is_locked
 * @property bool $is_pinned
 */
class DiscussionThread extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<DiscussionThreadFactory> */
    use HasFactory;

    protected $fillable = ['school_id', 'course_space_id', 'title', 'created_by', 'is_locked', 'is_pinned'];

    protected function casts(): array
    {
        return [
            'is_locked' => 'boolean',
            'is_pinned' => 'boolean',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return DiscussionThreadFactory::new();
    }

    /**
     * @return BelongsTo<CourseSpace, $this>
     */
    public function courseSpace(): BelongsTo
    {
        return $this->belongsTo(CourseSpace::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return HasMany<DiscussionPost, $this>
     */
    public function posts(): HasMany
    {
        return $this->hasMany(DiscussionPost::class, 'thread_id');
    }
}
