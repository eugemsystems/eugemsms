<?php

declare(strict_types=1);

namespace Modules\Comms\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Modules\Comms\Database\Factories\CalendarEventFactory;
use Modules\Core\Domain\Concerns\BelongsToSchool;
use Modules\Core\Domain\Concerns\HasUlid;

/**
 * Book I COM-06 §2 ⭐/BR-COM-06-001/002. See the owning migration's
 * docblock for why this is `BelongsToSchool` only, not
 * `BelongsToSession` — it is a rebuildable cache, never a
 * period-guarded source of truth.
 *
 * @property int $id
 * @property string $ulid
 * @property int $school_id
 * @property int $academic_year_id
 * @property int|null $term_id
 * @property string $source_type
 * @property int|null $source_module_id
 * @property string $title
 * @property string|null $description
 * @property Carbon $starts_at
 * @property Carbon|null $ends_at
 * @property bool $is_all_day
 * @property string|null $location
 * @property string $audience_scope
 * @property int|null $audience_scope_id
 * @property string|null $colour
 * @property bool $is_public
 * @property Carbon $rebuilt_at
 */
class CalendarEvent extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<CalendarEventFactory> */
    use HasFactory;

    use HasUlid;

    protected $fillable = [
        'school_id', 'academic_year_id', 'term_id', 'source_type', 'source_module_id',
        'title', 'description', 'starts_at', 'ends_at', 'is_all_day', 'location',
        'audience_scope', 'audience_scope_id', 'colour', 'is_public', 'rebuilt_at',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'is_all_day' => 'boolean',
            'is_public' => 'boolean',
            'rebuilt_at' => 'datetime',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return CalendarEventFactory::new();
    }

    /**
     * @return HasMany<EventRegistration, $this>
     */
    public function registrations(): HasMany
    {
        return $this->hasMany(EventRegistration::class);
    }

    public function isSourced(): bool
    {
        return $this->source_type !== 'manual';
    }
}
