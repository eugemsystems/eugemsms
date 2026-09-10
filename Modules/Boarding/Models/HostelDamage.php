<?php

declare(strict_types=1);

namespace Modules\Boarding\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\Boarding\Database\Factories\HostelDamageFactory;
use Modules\Core\Domain\Concerns\BelongsToSchool;
use Modules\Core\Domain\Concerns\HasUlid;
use Modules\Core\Models\Term;

/**
 * Book F BRD-01 §2/BR-BRD-01-012/013/014 ⭐. `ad_hoc_charge_ids`
 * records every `FIN-02` `AdHocCharge` id raised for this damage — a
 * shared-liability split raises one per liable learner
 * (AC-BRD-01-005).
 *
 * @property int $id
 * @property string $ulid
 * @property int $school_id
 * @property int $term_id
 * @property int $hostel_id
 * @property int|null $room_id
 * @property int|null $bed_id
 * @property int|null $inspection_id
 * @property string $damage_type
 * @property string $description
 * @property array<int, int>|null $photo_file_ids
 * @property int|null $estimated_cost_minor
 * @property int|null $actual_cost_minor
 * @property string $currency
 * @property string $liability
 * @property array<int, int>|null $liable_student_ids
 * @property string $charge_status
 * @property int|null $approval_request_id
 * @property array<int, int>|null $ad_hoc_charge_ids
 * @property int|null $work_order_id
 * @property int $reported_by
 * @property Carbon $reported_at
 */
class HostelDamage extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<HostelDamageFactory> */
    use HasFactory;

    use HasUlid;

    public $timestamps = false;

    protected $fillable = [
        'school_id', 'term_id', 'hostel_id', 'room_id', 'bed_id', 'inspection_id', 'damage_type',
        'description', 'photo_file_ids', 'estimated_cost_minor', 'actual_cost_minor', 'currency',
        'liability', 'liable_student_ids', 'charge_status', 'approval_request_id', 'ad_hoc_charge_ids',
        'work_order_id', 'reported_by', 'reported_at',
    ];

    protected function casts(): array
    {
        return [
            'photo_file_ids' => 'array',
            'liable_student_ids' => 'array',
            'ad_hoc_charge_ids' => 'array',
            'reported_at' => 'datetime',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return HostelDamageFactory::new();
    }

    /**
     * @return BelongsTo<Term, $this>
     */
    public function term(): BelongsTo
    {
        return $this->belongsTo(Term::class);
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
     * @return BelongsTo<HostelBed, $this>
     */
    public function bed(): BelongsTo
    {
        return $this->belongsTo(HostelBed::class, 'bed_id');
    }

    /**
     * @return BelongsTo<RoomInspection, $this>
     */
    public function inspection(): BelongsTo
    {
        return $this->belongsTo(RoomInspection::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function reportedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reported_by');
    }
}
