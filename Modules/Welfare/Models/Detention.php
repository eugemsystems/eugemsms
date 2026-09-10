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
use Modules\People\Models\Staff;
use Modules\People\Models\Student;
use Modules\Welfare\Database\Factories\DetentionFactory;

/**
 * Book G BRD-07 §2/BR-BRD-07-011/012 ⭐ — closes the `detention`
 * roll-status stub `BRD-02` §4 left open.
 *
 * @property int $id
 * @property int $school_id
 * @property int $term_id
 * @property int|null $sanction_id
 * @property int $student_id
 * @property Carbon $scheduled_date
 * @property string $starts_at
 * @property string $ends_at
 * @property string|null $venue
 * @property int|null $supervisor_staff_id
 * @property string|null $task_set
 * @property bool|null $attended
 * @property string|null $attendance_note
 * @property string $status
 */
class Detention extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<DetentionFactory> */
    use HasFactory;

    public const array CURRENTLY_SCHEDULED_STATUSES = ['scheduled'];

    public $timestamps = false;

    protected $fillable = [
        'school_id', 'term_id', 'sanction_id', 'student_id', 'scheduled_date', 'starts_at', 'ends_at',
        'venue', 'supervisor_staff_id', 'task_set', 'attended', 'attendance_note', 'status',
    ];

    protected function casts(): array
    {
        return [
            'scheduled_date' => 'date',
            'attended' => 'boolean',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return DetentionFactory::new();
    }

    /**
     * @return BelongsTo<Term, $this>
     */
    public function term(): BelongsTo
    {
        return $this->belongsTo(Term::class);
    }

    /**
     * @return BelongsTo<Sanction, $this>
     */
    public function sanction(): BelongsTo
    {
        return $this->belongsTo(Sanction::class);
    }

    /**
     * @return BelongsTo<Student, $this>
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    /**
     * @return BelongsTo<Staff, $this>
     */
    public function supervisorStaff(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'supervisor_staff_id');
    }
}
