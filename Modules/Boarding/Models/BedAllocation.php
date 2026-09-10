<?php

declare(strict_types=1);

namespace Modules\Boarding\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\Boarding\Database\Factories\BedAllocationFactory;
use Modules\Core\Domain\Concerns\BelongsToSchool;
use Modules\Core\Domain\Concerns\HasUlid;
use Modules\Core\Models\AcademicYear;
use Modules\Core\Models\Term;
use Modules\People\Models\Student;

/**
 * Book F BRD-01 §2/§4/BR-BRD-01-003/004/006 ⭐ — dated occupancy
 * history. Mid-term movement supersedes rather than overwrites: a row
 * is never re-pointed to a different bed, only ended (`effective_to`
 * set, `status` moved to `superseded`/`ended`) with a new row created
 * for the new bed.
 *
 * @property int $id
 * @property string $ulid
 * @property int $school_id
 * @property int $academic_year_id
 * @property int $term_id
 * @property int $student_id
 * @property int $bed_id
 * @property int $hostel_id
 * @property int $room_id
 * @property string $allocation_type
 * @property Carbon $effective_from
 * @property Carbon|null $effective_to
 * @property string $status
 * @property string|null $reason
 * @property int $allocated_by
 * @property int|null $confirmed_by
 */
class BedAllocation extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<BedAllocationFactory> */
    use HasFactory;

    use HasUlid;

    protected $fillable = [
        'school_id', 'academic_year_id', 'term_id', 'student_id', 'bed_id', 'hostel_id', 'room_id',
        'allocation_type', 'effective_from', 'effective_to', 'status', 'reason', 'allocated_by', 'confirmed_by',
    ];

    protected function casts(): array
    {
        return [
            'effective_from' => 'date',
            'effective_to' => 'date',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return BedAllocationFactory::new();
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
     * @return BelongsTo<HostelBed, $this>
     */
    public function bed(): BelongsTo
    {
        return $this->belongsTo(HostelBed::class, 'bed_id');
    }

    /**
     * @return BelongsTo<Hostel, $this>
     */
    public function hostel(): BelongsTo
    {
        return $this->belongsTo(Hostel::class);
    }

    /**
     * @return BelongsTo<HostelRoom, $this>
     */
    public function room(): BelongsTo
    {
        return $this->belongsTo(HostelRoom::class, 'room_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function allocatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'allocated_by');
    }
}
