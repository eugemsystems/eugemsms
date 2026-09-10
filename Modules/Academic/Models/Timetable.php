<?php

declare(strict_types=1);

namespace Modules\Academic\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Modules\Academic\Database\Factories\TimetableFactory;
use Modules\Core\Domain\Concerns\BelongsToSchool;
use Modules\Core\Domain\Concerns\HasUlid;
use Modules\Core\Models\AcademicYear;
use Modules\Core\Models\Term;

/**
 * Book E ACA-03 §2/BR-ACA-03-013/015 — a versioned published
 * schedule. `status` moves draft→generating→generated→review→
 * published→superseded. Publishing a new version supersedes the
 * prior one — both remain retrievable via `effective_from`/`effective_to`.
 *
 * @property int $id
 * @property string $ulid
 * @property int $school_id
 * @property int $academic_year_id
 * @property int $term_id
 * @property int $structure_id
 * @property string $name
 * @property int $version
 * @property string $status
 * @property Carbon|null $effective_from
 * @property Carbon|null $effective_to
 * @property int|null $generation_run_id
 * @property int $hard_violations
 * @property int $soft_violations
 * @property string|null $quality_score
 * @property int|null $published_by
 * @property Carbon|null $published_at
 * @property int $created_by
 */
class Timetable extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<TimetableFactory> */
    use HasFactory;

    use HasUlid;

    protected $fillable = [
        'school_id', 'academic_year_id', 'term_id', 'structure_id', 'name', 'version', 'status',
        'effective_from', 'effective_to', 'generation_run_id', 'hard_violations', 'soft_violations',
        'quality_score', 'published_by', 'published_at', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'effective_from' => 'date',
            'effective_to' => 'date',
            'published_at' => 'datetime',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return TimetableFactory::new();
    }

    /**
     * @return BelongsTo<AcademicYear, $this>
     */
    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    /**
     * @return BelongsTo<Term, $this>
     */
    public function term(): BelongsTo
    {
        return $this->belongsTo(Term::class);
    }

    /**
     * @return BelongsTo<PeriodStructure, $this>
     */
    public function structure(): BelongsTo
    {
        return $this->belongsTo(PeriodStructure::class, 'structure_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return HasMany<TimetableSlot, $this>
     */
    public function slots(): HasMany
    {
        return $this->hasMany(TimetableSlot::class);
    }
}
