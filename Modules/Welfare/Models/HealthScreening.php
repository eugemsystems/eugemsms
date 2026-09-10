<?php

declare(strict_types=1);

namespace Modules\Welfare\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\Core\Domain\Casts\SecondaryEncrypted;
use Modules\Core\Domain\Concerns\BelongsToSchool;
use Modules\Core\Models\Term;
use Modules\People\Models\Student;
use Modules\Welfare\Database\Factories\HealthScreeningFactory;

/**
 * Book G BRD-06 §2 — `results` is `SecondaryEncrypted`; callers
 * `json_encode`/`json_decode` it themselves (a JSON cast cannot be
 * layered on top of an encryption cast).
 *
 * @property int $id
 * @property int $school_id
 * @property int $term_id
 * @property int $student_id
 * @property string $screening_type
 * @property Carbon $screened_on
 * @property string|null $results
 * @property string $outcome
 * @property int|null $referral_id
 * @property Carbon|null $guardian_informed_at
 * @property string $screened_by
 */
class HealthScreening extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<HealthScreeningFactory> */
    use HasFactory;

    protected $fillable = [
        'school_id', 'term_id', 'student_id', 'screening_type', 'screened_on', 'results', 'outcome',
        'referral_id', 'guardian_informed_at', 'screened_by',
    ];

    protected function casts(): array
    {
        return [
            'screened_on' => 'date',
            'results' => SecondaryEncrypted::class,
            'guardian_informed_at' => 'datetime',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return HealthScreeningFactory::new();
    }

    /**
     * @return BelongsTo<Term, $this>
     */
    public function term(): BelongsTo
    {
        return $this->belongsTo(Term::class);
    }

    /**
     * @return BelongsTo<Student, $this>
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }
}
