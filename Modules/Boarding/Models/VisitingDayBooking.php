<?php

declare(strict_types=1);

namespace Modules\Boarding\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\Boarding\Database\Factories\VisitingDayBookingFactory;
use Modules\Core\Domain\Concerns\BelongsToSchool;
use Modules\People\Models\Guardian;
use Modules\People\Models\Student;

/**
 * Book F BRD-03 §2/BR-BRD-03-022.
 *
 * @property int $id
 * @property int $school_id
 * @property int $visiting_day_id
 * @property int $student_id
 * @property int $guardian_id
 * @property string $slot_starts_at
 * @property int $party_size
 * @property string $status
 * @property Carbon $booked_at
 */
class VisitingDayBooking extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<VisitingDayBookingFactory> */
    use HasFactory;

    public $timestamps = false;

    protected $fillable = ['school_id', 'visiting_day_id', 'student_id', 'guardian_id', 'slot_starts_at', 'party_size', 'status', 'booked_at'];

    protected function casts(): array
    {
        return [
            'booked_at' => 'datetime',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return VisitingDayBookingFactory::new();
    }

    /**
     * @return BelongsTo<VisitingDay, $this>
     */
    public function visitingDay(): BelongsTo
    {
        return $this->belongsTo(VisitingDay::class);
    }

    /**
     * @return BelongsTo<Student, $this>
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    /**
     * @return BelongsTo<Guardian, $this>
     */
    public function guardian(): BelongsTo
    {
        return $this->belongsTo(Guardian::class);
    }
}
