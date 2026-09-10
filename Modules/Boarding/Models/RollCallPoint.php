<?php

declare(strict_types=1);

namespace Modules\Boarding\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Boarding\Database\Factories\RollCallPointFactory;
use Modules\Core\Domain\Concerns\BelongsToSchool;
use Modules\Core\Domain\Concerns\HasUlid;

/**
 * Book F BRD-02 §2/BR-BRD-02-001. `hostel_id` null applies school-wide.
 *
 * @property int $id
 * @property string $ulid
 * @property int $school_id
 * @property int|null $hostel_id
 * @property string $code
 * @property string $name
 * @property string $scheduled_time
 * @property array<int, string> $applies_on_days
 * @property bool $applies_in_term_only
 * @property int $grace_minutes
 * @property bool $is_mandatory
 * @property int|null $escalation_profile_id
 * @property int|null $sort_order
 * @property bool $is_active
 */
class RollCallPoint extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<RollCallPointFactory> */
    use HasFactory;

    use HasUlid;

    public $timestamps = false;

    protected $fillable = [
        'school_id', 'hostel_id', 'code', 'name', 'scheduled_time', 'applies_on_days',
        'applies_in_term_only', 'grace_minutes', 'is_mandatory', 'escalation_profile_id',
        'sort_order', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'applies_on_days' => 'array',
            'applies_in_term_only' => 'boolean',
            'is_mandatory' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return RollCallPointFactory::new();
    }

    /**
     * @return BelongsTo<Hostel, $this>
     */
    public function hostel(): BelongsTo
    {
        return $this->belongsTo(Hostel::class);
    }

    /**
     * @return BelongsTo<EscalationProfile, $this>
     */
    public function escalationProfile(): BelongsTo
    {
        return $this->belongsTo(EscalationProfile::class, 'escalation_profile_id');
    }
}
