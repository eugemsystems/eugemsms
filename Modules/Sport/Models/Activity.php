<?php

declare(strict_types=1);

namespace Modules\Sport\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Academic\Models\Venue;
use Modules\Core\Domain\Concerns\BelongsToSchool;
use Modules\Core\Domain\Concerns\HasUlid;
use Modules\Finance\Models\FeeComponent;
use Modules\People\Models\Staff;
use Modules\Sport\Database\Factories\ActivityFactory;

/**
 * Book H2 OPS-07 §2.
 *
 * @property int $id
 * @property string $ulid
 * @property int $school_id
 * @property string $code
 * @property string $name
 * @property string $activity_type
 * @property string|null $season
 * @property string $gender_scope
 * @property int|null $min_grade_ordinal
 * @property int|null $max_grade_ordinal
 * @property int|null $coach_staff_id
 * @property int|null $fee_component_id
 * @property bool $requires_medical_clearance
 * @property bool $requires_guardian_consent
 * @property int|null $max_participants
 * @property int|null $venue_id
 * @property bool $is_active
 */
class Activity extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<ActivityFactory> */
    use HasFactory;

    use HasUlid;

    public $timestamps = false;

    protected $fillable = [
        'school_id', 'code', 'name', 'activity_type', 'season', 'gender_scope',
        'min_grade_ordinal', 'max_grade_ordinal', 'coach_staff_id', 'fee_component_id',
        'requires_medical_clearance', 'requires_guardian_consent', 'max_participants',
        'venue_id', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'requires_medical_clearance' => 'boolean',
            'requires_guardian_consent' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return ActivityFactory::new();
    }

    /**
     * @return BelongsTo<Staff, $this>
     */
    public function coach(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'coach_staff_id');
    }

    /**
     * @return BelongsTo<FeeComponent, $this>
     */
    public function feeComponent(): BelongsTo
    {
        return $this->belongsTo(FeeComponent::class);
    }

    /**
     * @return BelongsTo<Venue, $this>
     */
    public function venue(): BelongsTo
    {
        return $this->belongsTo(Venue::class);
    }
}
