<?php

declare(strict_types=1);

namespace Modules\Sport\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\Core\Domain\Concerns\BelongsToSchool;
use Modules\People\Models\Student;
use Modules\Sport\Database\Factories\EquipmentIssueFactory;
use Modules\Stores\Models\FixedAsset;

/**
 * Book H2 OPS-07 §3/BR-OPS-07-011 — see the owning migration's
 * docblock for why this table is a new, additive gap-fill rather than
 * a reuse of `Modules\Stores`'s staff-only custodian mechanism.
 *
 * @property int $id
 * @property int $school_id
 * @property int $activity_id
 * @property int $asset_id
 * @property int $student_id
 * @property Carbon $issued_at
 * @property int $issued_by
 * @property Carbon|null $expected_return_on
 * @property Carbon|null $returned_at
 * @property string|null $condition_on_return
 * @property string|null $notes
 */
class EquipmentIssue extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<EquipmentIssueFactory> */
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'school_id', 'activity_id', 'asset_id', 'student_id', 'issued_at', 'issued_by',
        'expected_return_on', 'returned_at', 'condition_on_return', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'issued_at' => 'datetime',
            'expected_return_on' => 'date',
            'returned_at' => 'datetime',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return EquipmentIssueFactory::new();
    }

    /**
     * @return BelongsTo<Activity, $this>
     */
    public function activity(): BelongsTo
    {
        return $this->belongsTo(Activity::class);
    }

    /**
     * @return BelongsTo<FixedAsset, $this>
     */
    public function asset(): BelongsTo
    {
        return $this->belongsTo(FixedAsset::class);
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
    public function issuedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by');
    }
}
