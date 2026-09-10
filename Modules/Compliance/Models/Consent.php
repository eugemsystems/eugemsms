<?php

declare(strict_types=1);

namespace Modules\Compliance\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\Compliance\Database\Factories\ConsentFactory;
use Modules\Core\Domain\Concerns\BelongsToSchool;
use Modules\Core\Domain\Concerns\HasUlid;
use Modules\Core\Domain\Exceptions\InvalidStateTransitionException;
use Modules\People\Models\Staff;

/**
 * Book H3 CMP-03 §2/BR-CMP-03-001 ⭐. Append-only: withdrawal is a new
 * STATE on this same row (`withdrawn_at`/`withdrawn_by`/
 * `withdrawal_reason`), never a new row and never a deletion — so
 * those three fields (plus `updated_at`) are the only ones this guard
 * allows to change after creation; every other column, and the row
 * itself, is frozen forever.
 *
 * @property int $id
 * @property string $ulid
 * @property int $school_id
 * @property int $consent_type_id
 * @property string $subject_type
 * @property int $subject_id
 * @property string $granted_by_type
 * @property int $granted_by_id
 * @property bool $granted
 * @property Carbon $granted_at
 * @property string $method
 * @property string $notice_version
 * @property int|null $document_file_id
 * @property int|null $witness_staff_id
 * @property string|null $ip_address
 * @property Carbon|null $expires_on
 * @property Carbon|null $withdrawn_at
 * @property int|null $withdrawn_by
 * @property string|null $withdrawal_reason
 */
class Consent extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<ConsentFactory> */
    use HasFactory;

    use HasUlid;

    private const array MUTABLE_AFTER_CREATE = ['withdrawn_at', 'withdrawn_by', 'withdrawal_reason', 'updated_at'];

    protected $fillable = [
        'school_id', 'consent_type_id', 'subject_type', 'subject_id', 'granted_by_type', 'granted_by_id',
        'granted', 'granted_at', 'method', 'notice_version', 'document_file_id', 'witness_staff_id',
        'ip_address', 'expires_on', 'withdrawn_at', 'withdrawn_by', 'withdrawal_reason',
    ];

    protected function casts(): array
    {
        return [
            'granted' => 'boolean',
            'granted_at' => 'datetime',
            'expires_on' => 'date',
            'withdrawn_at' => 'datetime',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return ConsentFactory::new();
    }

    protected static function booted(): void
    {
        static::updating(function (Model $model): void {
            $dirty = array_keys($model->getDirty());
            $illegal = array_diff($dirty, self::MUTABLE_AFTER_CREATE);

            if ($illegal !== []) {
                throw new InvalidStateTransitionException(
                    'A consents row is append-only — only withdrawn_at, withdrawn_by, or withdrawal_reason may change after creation (BR-CMP-03-001).',
                    ['dirty' => $illegal],
                );
            }
        });

        static::deleting(function (): void {
            throw new InvalidStateTransitionException('consents is append-only — a consent record is never deleted.');
        });
    }

    /**
     * @return BelongsTo<ConsentType, $this>
     */
    public function consentType(): BelongsTo
    {
        return $this->belongsTo(ConsentType::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function withdrawnBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'withdrawn_by');
    }

    /**
     * @return BelongsTo<Staff, $this>
     */
    public function witnessStaff(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'witness_staff_id');
    }

    public function isWithdrawn(): bool
    {
        return $this->withdrawn_at !== null;
    }
}
