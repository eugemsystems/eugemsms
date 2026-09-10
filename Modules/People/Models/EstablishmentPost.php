<?php

declare(strict_types=1);

namespace Modules\People\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Core\Domain\Concerns\BelongsToSchool;
use Modules\People\Database\Factories\EstablishmentPostFactory;

/**
 * Book C PPL-04 §2/BR-PPL-04-004 — approved staffing levels.
 * `filled_count` is maintained only by `FillEstablishmentPostAction`/
 * `VacateEstablishmentPostAction`.
 *
 * @property int $id
 * @property int $school_id
 * @property int|null $department_id
 * @property string $title
 * @property string|null $grade
 * @property int $approved_count
 * @property int $filled_count
 * @property bool $is_teaching
 * @property bool $is_active
 */
class EstablishmentPost extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<EstablishmentPostFactory> */
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'school_id', 'department_id', 'title', 'grade', 'approved_count', 'filled_count', 'is_teaching', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_teaching' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return EstablishmentPostFactory::new();
    }

    public function hasVacancy(): bool
    {
        return $this->filled_count < $this->approved_count;
    }

    /**
     * @return BelongsTo<Department, $this>
     */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    /**
     * @return HasMany<Staff, $this>
     */
    public function staff(): HasMany
    {
        return $this->hasMany(Staff::class, 'post_id');
    }
}
