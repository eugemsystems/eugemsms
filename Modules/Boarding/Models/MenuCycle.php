<?php

declare(strict_types=1);

namespace Modules\Boarding\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Modules\Boarding\Database\Factories\MenuCycleFactory;
use Modules\Core\Domain\Concerns\BelongsToSchool;
use Modules\Core\Domain\Concerns\HasUlid;
use Modules\Core\Models\AcademicYear;

/**
 * Book F BRD-04 §2.
 *
 * @property int $id
 * @property string $ulid
 * @property int $school_id
 * @property int $academic_year_id
 * @property string $name
 * @property int $cycle_length_days
 * @property Carbon|null $starts_on
 * @property Carbon|null $ends_on
 * @property bool $is_active
 */
class MenuCycle extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<MenuCycleFactory> */
    use HasFactory;

    use HasUlid;

    public $timestamps = false;

    protected $fillable = ['school_id', 'academic_year_id', 'name', 'cycle_length_days', 'starts_on', 'ends_on', 'is_active'];

    protected function casts(): array
    {
        return [
            'starts_on' => 'date',
            'ends_on' => 'date',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return MenuCycleFactory::new();
    }

    /**
     * @return BelongsTo<AcademicYear, $this>
     */
    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    /**
     * @return HasMany<MenuDay, $this>
     */
    public function days(): HasMany
    {
        return $this->hasMany(MenuDay::class, 'cycle_id');
    }
}
