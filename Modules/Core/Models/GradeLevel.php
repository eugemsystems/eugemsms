<?php

declare(strict_types=1);

namespace Modules\Core\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Modules\Core\Database\Factories\GradeLevelFactory;
use Modules\Core\Domain\Concerns\BelongsToSchool;
use Modules\Core\Domain\Concerns\HasUlid;

/**
 * Book A CORE-02 §2. BR-CORE-02-003: `ordinal` is unique per school and
 * defines the promotion sequence — promotion moves a learner from ordinal
 * n to n+1.
 *
 * @property int $id
 * @property string $ulid
 * @property int $school_id
 * @property int $section_id
 * @property string $code
 * @property string $name
 * @property int $ordinal
 * @property bool $is_exam_level
 * @property bool $is_entry_level
 * @property bool $is_exit_level
 * @property int|null $capacity
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read SchoolSection $section
 * @property-read Collection<int, SchoolClass> $classes
 */
class GradeLevel extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<GradeLevelFactory> */
    use HasFactory;

    use HasUlid;

    protected $fillable = [
        'school_id',
        'section_id',
        'code',
        'name',
        'ordinal',
        'is_exam_level',
        'is_entry_level',
        'is_exit_level',
        'capacity',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'ordinal' => 'integer',
            'is_exam_level' => 'boolean',
            'is_entry_level' => 'boolean',
            'is_exit_level' => 'boolean',
            'capacity' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return GradeLevelFactory::new();
    }

    /**
     * @return BelongsTo<SchoolSection, $this>
     */
    public function section(): BelongsTo
    {
        return $this->belongsTo(SchoolSection::class, 'section_id');
    }

    /**
     * @return HasMany<SchoolClass, $this>
     */
    public function classes(): HasMany
    {
        return $this->hasMany(SchoolClass::class);
    }
}
