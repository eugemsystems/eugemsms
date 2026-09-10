<?php

declare(strict_types=1);

namespace Modules\Core\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Modules\Core\Database\Factories\SchoolSectionFactory;
use Modules\Core\Domain\Concerns\BelongsToSchool;
use Modules\Core\Domain\Concerns\HasUlid;

/**
 * Book A CORE-02 §2 — 'INF' Infant, 'JUN' Junior, 'LSEC' Lower Secondary,
 * 'USEC' Upper Secondary, 'SIXTH' Sixth Form. BR-CORE-02-002: a school
 * must have at least one section before any grade level can be created.
 *
 * @property int $id
 * @property string $ulid
 * @property int $school_id
 * @property string $code
 * @property string $name
 * @property string $type
 * @property int $sort_order
 * @property int|null $head_user_id
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User|null $head
 * @property-read Collection<int, GradeLevel> $gradeLevels
 */
class SchoolSection extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<SchoolSectionFactory> */
    use HasFactory;

    use HasUlid;

    protected $fillable = [
        'school_id',
        'code',
        'name',
        'type',
        'sort_order',
        'head_user_id',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return SchoolSectionFactory::new();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function head(): BelongsTo
    {
        return $this->belongsTo(User::class, 'head_user_id');
    }

    /**
     * @return HasMany<GradeLevel, $this>
     */
    public function gradeLevels(): HasMany
    {
        return $this->hasMany(GradeLevel::class, 'section_id');
    }
}
