<?php

declare(strict_types=1);

namespace Modules\Comms\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\Comms\Database\Factories\ConsultationBookingFactory;
use Modules\Core\Domain\Concerns\BelongsToSchool;
use Modules\People\Models\Guardian;
use Modules\People\Models\Student;

/**
 * Book I COM-07 §2 ⭐/BR-COM-07-005 (AC-COM-07-004). See the owning
 * migration's docblock for the `(window_id, slot_starts_at, status)`
 * unique index this model's double-booking guarantee rests on.
 *
 * @property int $id
 * @property int $school_id
 * @property int $window_id
 * @property int $guardian_id
 * @property int $student_id
 * @property Carbon $slot_starts_at
 * @property int|null $meeting_id
 * @property string $status
 */
class ConsultationBooking extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<ConsultationBookingFactory> */
    use HasFactory;

    protected $fillable = [
        'school_id', 'window_id', 'guardian_id', 'student_id', 'slot_starts_at', 'meeting_id', 'status',
    ];

    protected function casts(): array
    {
        return [
            'slot_starts_at' => 'datetime',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return ConsultationBookingFactory::new();
    }

    /**
     * @return BelongsTo<ConsultationWindow, $this>
     */
    public function window(): BelongsTo
    {
        return $this->belongsTo(ConsultationWindow::class, 'window_id');
    }

    /**
     * @return BelongsTo<Guardian, $this>
     */
    public function guardian(): BelongsTo
    {
        return $this->belongsTo(Guardian::class);
    }

    /**
     * @return BelongsTo<Student, $this>
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    /**
     * @return BelongsTo<ScheduledMeeting, $this>
     */
    public function meeting(): BelongsTo
    {
        return $this->belongsTo(ScheduledMeeting::class);
    }
}
