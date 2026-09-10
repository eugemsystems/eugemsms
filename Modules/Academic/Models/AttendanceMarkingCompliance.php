<?php

declare(strict_types=1);

namespace Modules\Academic\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\Academic\Database\Factories\AttendanceMarkingComplianceFactory;
use Modules\Core\Domain\Concerns\BelongsToSchool;
use Modules\Core\Models\Term;
use Modules\People\Models\Staff;

/**
 * Book D ACA-04 §2/BR-ACA-04-014 — a cache, rebuilt by
 * `RecordMarkingComplianceAction`.
 *
 * @property int $id
 * @property int $school_id
 * @property int $term_id
 * @property int $staff_id
 * @property Carbon $session_date
 * @property int $expected_sessions
 * @property int $marked_sessions
 * @property int $marked_late_sessions
 * @property string|null $compliance_percent
 */
class AttendanceMarkingCompliance extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<AttendanceMarkingComplianceFactory> */
    use HasFactory;

    protected $table = 'attendance_marking_compliance';

    public $timestamps = false;

    protected $fillable = [
        'school_id', 'term_id', 'staff_id', 'session_date', 'expected_sessions',
        'marked_sessions', 'marked_late_sessions', 'compliance_percent',
    ];

    protected function casts(): array
    {
        return [
            'session_date' => 'date',
            'compliance_percent' => 'decimal:2',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return AttendanceMarkingComplianceFactory::new();
    }

    /**
     * @return BelongsTo<Term, $this>
     */
    public function term(): BelongsTo
    {
        return $this->belongsTo(Term::class);
    }

    /**
     * @return BelongsTo<Staff, $this>
     */
    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class);
    }
}
