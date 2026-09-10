<?php

declare(strict_types=1);

namespace Modules\People\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\Core\Domain\Concerns\BelongsToSchool;
use Modules\Core\Domain\Concerns\HasUlid;
use Modules\People\Database\Factories\StaffDisciplinaryCaseFactory;

/**
 * Book C PPL-04 §2/BR-PPL-04-020 ⭐. `is_confidential` defaults true
 * (BR-PPL-04-020: "confidential by default"). This model carries no
 * query scope restricting who may read a row — "visible only to the
 * head, the deputy, and the case handler" is an authorization
 * decision made by the caller (screen/API layer), matching this
 * codebase's established convention that permission checks live
 * outside the Action/model, not inside it. What this pass DOES
 * implement directly: every read goes through
 * `ViewDisciplinaryCaseAction`, which writes the access to
 * `data_access_log` via Core's existing `RecordDataAccessAction` —
 * never reading a case model directly bypasses that.
 *
 * @property int $id
 * @property string $ulid
 * @property int $school_id
 * @property int $staff_id
 * @property string $case_number
 * @property string $category
 * @property string $description
 * @property Carbon $incident_date
 * @property int $reported_by
 * @property string $stage
 * @property string|null $outcome
 * @property Carbon|null $outcome_date
 * @property bool $is_confidential
 */
class StaffDisciplinaryCase extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<StaffDisciplinaryCaseFactory> */
    use HasFactory;

    use HasUlid;

    public $timestamps = false;

    protected $fillable = [
        'school_id', 'staff_id', 'case_number', 'category', 'description', 'incident_date',
        'reported_by', 'stage', 'outcome', 'outcome_date', 'is_confidential', 'created_at',
    ];

    protected function casts(): array
    {
        return [
            'incident_date' => 'date',
            'outcome_date' => 'date',
            'is_confidential' => 'boolean',
            'created_at' => 'datetime',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return StaffDisciplinaryCaseFactory::new();
    }

    /**
     * @return BelongsTo<Staff, $this>
     */
    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class);
    }
}
