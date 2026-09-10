<?php

declare(strict_types=1);

namespace Modules\Welfare\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\Core\Domain\Concerns\BelongsToSchool;
use Modules\Core\Models\Term;
use Modules\People\Models\Student;
use Modules\Welfare\Database\Factories\BehaviourPointBalanceFactory;

/**
 * Book G BRD-07 §2/BR-BRD-07-002/015 — CACHE, rebuilt from
 * `behaviour_records`.
 *
 * @property int $id
 * @property int $school_id
 * @property int $student_id
 * @property int $term_id
 * @property int $merit_points
 * @property int $demerit_points
 * @property int $net_points
 * @property int $record_count
 * @property string|null $conduct_grade
 * @property Carbon|null $rebuilt_at
 */
class BehaviourPointBalance extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<BehaviourPointBalanceFactory> */
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'school_id', 'student_id', 'term_id', 'merit_points', 'demerit_points', 'net_points',
        'record_count', 'conduct_grade', 'rebuilt_at',
    ];

    protected function casts(): array
    {
        return [
            'rebuilt_at' => 'datetime',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return BehaviourPointBalanceFactory::new();
    }

    /**
     * @return BelongsTo<Student, $this>
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    /**
     * @return BelongsTo<Term, $this>
     */
    public function term(): BelongsTo
    {
        return $this->belongsTo(Term::class);
    }
}
