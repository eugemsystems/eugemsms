<?php

declare(strict_types=1);

namespace Modules\Academic\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Academic\Database\Factories\LibraryItemFactory;
use Modules\Core\Domain\Concerns\BelongsToSchool;
use Modules\Core\Domain\Concerns\HasUlid;
use Modules\Core\Models\File;
use Modules\Core\Models\GradeLevel;

/**
 * Book K ACA-10 §2. A catalogue title — see `LibraryCopy` for a
 * physical, individually-accessioned instance of it.
 *
 * @property int $id
 * @property string $ulid
 * @property int $school_id
 * @property string|null $isbn
 * @property string $title
 * @property string|null $author
 * @property string|null $publisher
 * @property string|null $edition
 * @property string|null $classification
 * @property string $item_category
 * @property int|null $subject_id
 * @property int|null $grade_level_id
 * @property int|null $replacement_cost_minor
 * @property string|null $currency
 * @property int|null $cover_image_file_id
 * @property string|null $digital_resource_url
 * @property bool $is_active
 */
class LibraryItem extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<LibraryItemFactory> */
    use HasFactory;

    use HasUlid;

    protected $fillable = [
        'school_id', 'isbn', 'title', 'author', 'publisher', 'edition', 'classification',
        'item_category', 'subject_id', 'grade_level_id', 'replacement_cost_minor', 'currency',
        'cover_image_file_id', 'digital_resource_url', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'replacement_cost_minor' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return LibraryItemFactory::new();
    }

    /**
     * @return BelongsTo<Subject, $this>
     */
    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    /**
     * @return BelongsTo<GradeLevel, $this>
     */
    public function gradeLevel(): BelongsTo
    {
        return $this->belongsTo(GradeLevel::class);
    }

    /**
     * @return BelongsTo<File, $this>
     */
    public function coverImage(): BelongsTo
    {
        return $this->belongsTo(File::class, 'cover_image_file_id');
    }

    /**
     * @return HasMany<LibraryCopy, $this>
     */
    public function copies(): HasMany
    {
        return $this->hasMany(LibraryCopy::class, 'item_id');
    }
}
