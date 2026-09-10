<?php

declare(strict_types=1);

namespace Modules\Boarding\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\Boarding\Database\Factories\HostelWaitingListEntryFactory;
use Modules\Core\Domain\Concerns\BelongsToSchool;
use Modules\Core\Models\AcademicYear;
use Modules\Core\Models\Term;
use Modules\People\Models\Student;

/**
 * Book F BRD-01 §2/BR-BRD-01-010. Maps to the `hostel_waiting_list`
 * table — named `Entry` here since `WaitingList` reads better as a
 * concept than a row.
 *
 * @property int $id
 * @property int $school_id
 * @property int $academic_year_id
 * @property int $term_id
 * @property int $student_id
 * @property int|null $preferred_hostel_id
 * @property string|null $priority_score
 * @property int|null $position
 * @property string|null $reason
 * @property string $status
 * @property Carbon $added_at
 */
class HostelWaitingListEntry extends Model
{
    protected $table = 'hostel_waiting_list';

    use BelongsToSchool;

    /** @use HasFactory<HostelWaitingListEntryFactory> */
    use HasFactory;

    public $timestamps = false;

    protected $fillable = ['school_id', 'academic_year_id', 'term_id', 'student_id', 'preferred_hostel_id', 'priority_score', 'position', 'reason', 'status', 'added_at'];

    protected function casts(): array
    {
        return [
            'added_at' => 'datetime',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return HostelWaitingListEntryFactory::new();
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
     * @return BelongsTo<Student, $this>
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    /**
     * @return BelongsTo<Hostel, $this>
     */
    public function preferredHostel(): BelongsTo
    {
        return $this->belongsTo(Hostel::class, 'preferred_hostel_id');
    }
}
