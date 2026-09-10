<?php

declare(strict_types=1);

namespace Modules\Boarding\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\Boarding\Database\Factories\RollCallRecordFactory;
use Modules\Core\Domain\Concerns\BelongsToSchool;
use Modules\People\Models\Student;

/**
 * Book F BRD-02 §2/§4/BR-BRD-02-003/004. `is_auto_populated` +
 * `source_reference` let the housemaster see why a learner is marked
 * accounted for; overriding it is a normal update carrying a
 * mandatory `note`.
 *
 * @property int $id
 * @property int $school_id
 * @property int $roll_call_id
 * @property int $student_id
 * @property Carbon $roll_date
 * @property string $status
 * @property bool $is_auto_populated
 * @property string|null $source_reference
 * @property int|null $marked_by
 * @property Carbon $marked_at
 * @property string|null $device_source
 * @property string|null $note
 * @property Carbon|null $resolved_at
 * @property int|null $resolved_by
 * @property string|null $resolution_note
 */
class RollCallRecord extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<RollCallRecordFactory> */
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'school_id', 'roll_call_id', 'student_id', 'roll_date', 'status', 'is_auto_populated',
        'source_reference', 'marked_by', 'marked_at', 'device_source', 'note',
        'resolved_at', 'resolved_by', 'resolution_note',
    ];

    protected function casts(): array
    {
        return [
            'roll_date' => 'date',
            'is_auto_populated' => 'boolean',
            'marked_at' => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return RollCallRecordFactory::new();
    }

    /**
     * @return BelongsTo<RollCall, $this>
     */
    public function rollCall(): BelongsTo
    {
        return $this->belongsTo(RollCall::class, 'roll_call_id');
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
    public function markedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'marked_by');
    }
}
