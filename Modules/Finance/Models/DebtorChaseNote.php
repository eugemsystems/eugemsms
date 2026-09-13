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
use Modules\Finance\Database\Factories\DebtorChaseNoteFactory;
use Modules\People\Models\Student;

/**
 * `Finance\Debtors\Workbench`'s own call log (Book B FIN-03 §5) — see
 * this table's migration docblock for why it exists outside the
 * spec's own §2 data model. Append-only: a correction is a new note,
 * never an edit to what was actually said on a call.
 *
 * @property int $id
 * @property int $school_id
 * @property int $student_id
 * @property string $outcome
 * @property string $note
 * @property Carbon|null $next_action_on
 * @property int $recorded_by
 * @property Carbon $created_at
 */
class DebtorChaseNote extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<DebtorChaseNoteFactory> */
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'school_id', 'student_id', 'outcome', 'note', 'next_action_on', 'recorded_by', 'created_at',
    ];

    protected function casts(): array
    {
        return [
            'next_action_on' => 'date',
            'created_at' => 'datetime',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return DebtorChaseNoteFactory::new();
    }

    protected static function booted(): void
    {
        static::updating(function (): never {
            throw new InvalidStateTransitionException('debtor_chase_notes is append-only — a correction is a new note.');
        });

        static::deleting(function (): never {
            throw new InvalidStateTransitionException('debtor_chase_notes rows are never deleted.');
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
     * @return BelongsTo<User, $this>
     */
    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
