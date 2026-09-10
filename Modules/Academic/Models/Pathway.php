<?php

declare(strict_types=1);

namespace Modules\Academic\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Academic\Database\Factories\PathwayFactory;
use Modules\Core\Domain\Concerns\BelongsToSchool;
use Modules\Core\Domain\Concerns\HasUlid;

/**
 * Book D ACA-01 §2 🇿🇼/BR-ACA-01-013 — the two-route model. Applies
 * from `applies_from_level_ordinal` upward; below that, a learner's
 * `pathway` stays null and no pathway rule applies to them.
 *
 * @property int $id
 * @property string $ulid
 * @property int $school_id
 * @property int $framework_id
 * @property string $code
 * @property string $name
 * @property string|null $description
 * @property int $applies_from_level_ordinal
 * @property bool $is_default
 * @property bool $is_active
 */
class Pathway extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<PathwayFactory> */
    use HasFactory;

    use HasUlid;

    public $timestamps = false;

    protected $fillable = [
        'school_id', 'framework_id', 'code', 'name', 'description',
        'applies_from_level_ordinal', 'is_default', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return PathwayFactory::new();
    }

    /**
     * @return BelongsTo<CurriculumFramework, $this>
     */
    public function framework(): BelongsTo
    {
        return $this->belongsTo(CurriculumFramework::class, 'framework_id');
    }
}
