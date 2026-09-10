<?php

declare(strict_types=1);

namespace Modules\Welfare\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Modules\Core\Domain\Casts\SecondaryEncrypted;
use Modules\Core\Domain\Concerns\BelongsToSchool;
use Modules\Core\Domain\Concerns\HasUlid;
use Modules\Core\Domain\Exceptions\InvalidStateTransitionException;
use Modules\People\Models\Staff;
use Modules\People\Models\Student;
use Modules\Welfare\Database\Factories\SafeguardingCaseFactory;

/**
 * Book G BRD-08 §2/§3 ⭐⭐/BR-BRD-08-001/002/007. No deletion path
 * exists anywhere in the code for this model — the guard below is
 * belt-and-suspenders on top of that fact, not the only protection.
 *
 * @property int $id
 * @property string $ulid
 * @property int $school_id
 * @property string $case_reference
 * @property int $student_id
 * @property Carbon $opened_at
 * @property int $opened_by
 * @property int $lead_staff_id
 * @property string $category
 * @property string $risk_level
 * @property string $summary
 * @property string $status
 * @property bool $external_agency_involved
 * @property bool|null $guardians_informed
 * @property string|null $guardians_not_informed_reason
 * @property Carbon|null $next_review_on
 * @property Carbon|null $closed_at
 * @property int|null $closed_by
 * @property string|null $closure_summary
 * @property Carbon|null $retention_until
 */
class SafeguardingCase extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<SafeguardingCaseFactory> */
    use HasFactory;

    use HasUlid;

    public $timestamps = false;

    protected $fillable = [
        'school_id', 'case_reference', 'student_id', 'opened_at', 'opened_by', 'lead_staff_id', 'category',
        'risk_level', 'summary', 'status', 'external_agency_involved', 'guardians_informed',
        'guardians_not_informed_reason', 'next_review_on', 'closed_at', 'closed_by', 'closure_summary',
        'retention_until',
    ];

    protected function casts(): array
    {
        return [
            'opened_at' => 'datetime',
            'summary' => SecondaryEncrypted::class,
            'external_agency_involved' => 'boolean',
            'guardians_informed' => 'boolean',
            'guardians_not_informed_reason' => SecondaryEncrypted::class,
            'next_review_on' => 'date',
            'closed_at' => 'datetime',
            'closure_summary' => SecondaryEncrypted::class,
            'retention_until' => 'date',
        ];
    }

    protected static function booted(): void
    {
        static::deleting(function (): never {
            throw new InvalidStateTransitionException('safeguarding_cases can never be deleted at any permission level (BR-BRD-08-007).');
        });
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return SafeguardingCaseFactory::new();
    }

    /**
     * @return BelongsTo<Student, $this>
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    /**
     * @return BelongsTo<Staff, $this>
     */
    public function leadStaff(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'lead_staff_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function openedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'opened_by');
    }

    /**
     * @return HasMany<CaseAccessGrant, $this>
     */
    public function accessGrants(): HasMany
    {
        return $this->hasMany(CaseAccessGrant::class, 'case_id');
    }

    /**
     * @return HasMany<CaseEntry, $this>
     */
    public function entries(): HasMany
    {
        return $this->hasMany(CaseEntry::class, 'case_id');
    }

    /**
     * BR-BRD-08-002 — an active, unexpired grant for this user, or null.
     */
    public function activeGrantFor(User $user): ?CaseAccessGrant
    {
        return $this->accessGrants()
            ->where('user_id', $user->id)
            ->whereNull('revoked_at')
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->first();
    }
}
