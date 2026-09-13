<?php

declare(strict_types=1);

namespace Modules\Finance\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\Core\Domain\Concerns\BelongsToSchool;
use Modules\Core\Domain\Exceptions\InvalidStateTransitionException;
use Modules\Core\Models\Term;
use Modules\Finance\Database\Factories\ReportGateOverrideFactory;
use Modules\People\Models\Student;

/**
 * Book B FIN-03 §2/BR-FIN-03-018. One row per (school, student, term)
 * — see this table's migration docblock for why a fresh override is
 * required every term rather than a standing exemption. Append-only:
 * an override is either granted or it isn't; there is nothing to edit.
 *
 * @property int $id
 * @property int $school_id
 * @property int $student_id
 * @property int $term_id
 * @property string $reason
 * @property int $granted_by
 * @property Carbon $created_at
 */
class ReportGateOverride extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<ReportGateOverrideFactory> */
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'school_id', 'student_id', 'term_id', 'reason', 'granted_by', 'created_at',
    ];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return ReportGateOverrideFactory::new();
    }

    protected static function booted(): void
    {
        static::updating(function (): never {
            throw new InvalidStateTransitionException('report_gate_overrides is append-only — grant a new one, never edit an existing one.');
        });

        static::deleting(function (): never {
            throw new InvalidStateTransitionException('report_gate_overrides rows are never deleted.');
        });
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

    /**
     * @return BelongsTo<User, $this>
     */
    public function grantedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'granted_by');
    }
}
