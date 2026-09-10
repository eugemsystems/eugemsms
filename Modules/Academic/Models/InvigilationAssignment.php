<?php

declare(strict_types=1);

namespace Modules\Academic\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Academic\Database\Factories\InvigilationAssignmentFactory;
use Modules\Core\Domain\Concerns\BelongsToSchool;
use Modules\People\Models\DutyAssignment;
use Modules\People\Models\Staff;

/**
 * Book E ACA-07 §2/BR-ACA-07-010/011. No `ulid` — always reached
 * through its paper.
 *
 * @property int $id
 * @property int $school_id
 * @property int $paper_id
 * @property int $venue_id
 * @property int $staff_id
 * @property string $role
 * @property int|null $duty_assignment_id
 * @property bool $confirmed
 * @property bool|null $attended
 * @property bool $report_submitted
 * @property string|null $report_notes
 */
class InvigilationAssignment extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<InvigilationAssignmentFactory> */
    use HasFactory;

    public $timestamps = false;

    protected $fillable = ['school_id', 'paper_id', 'venue_id', 'staff_id', 'role', 'duty_assignment_id', 'confirmed', 'attended', 'report_submitted', 'report_notes'];

    protected function casts(): array
    {
        return [
            'confirmed' => 'boolean',
            'attended' => 'boolean',
            'report_submitted' => 'boolean',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return InvigilationAssignmentFactory::new();
    }

    /**
     * @return BelongsTo<ExaminationPaper, $this>
     */
    public function paper(): BelongsTo
    {
        return $this->belongsTo(ExaminationPaper::class, 'paper_id');
    }

    /**
     * @return BelongsTo<Venue, $this>
     */
    public function venue(): BelongsTo
    {
        return $this->belongsTo(Venue::class);
    }

    /**
     * @return BelongsTo<Staff, $this>
     */
    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class);
    }

    /**
     * @return BelongsTo<DutyAssignment, $this>
     */
    public function dutyAssignment(): BelongsTo
    {
        return $this->belongsTo(DutyAssignment::class);
    }
}
