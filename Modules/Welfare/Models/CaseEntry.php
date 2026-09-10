<?php

declare(strict_types=1);

namespace Modules\Welfare\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\Core\Domain\Casts\SecondaryEncrypted;
use Modules\Core\Domain\Concerns\BelongsToSchool;
use Modules\Core\Domain\Concerns\HasUlid;
use Modules\Core\Domain\Exceptions\InvalidStateTransitionException;
use Modules\Welfare\Database\Factories\CaseEntryFactory;

/**
 * Book G BRD-08 §2/BR-BRD-08-008 ⭐ — APPEND-ONLY. No UPDATE, no
 * DELETE — a correction is a new entry referencing the earlier one.
 * `content` is `SecondaryEncrypted`.
 *
 * @property int $id
 * @property string $ulid
 * @property int $school_id
 * @property int $case_id
 * @property string $entry_type
 * @property Carbon $entry_at
 * @property Carbon $recorded_at
 * @property string $content
 * @property bool $is_learner_account
 * @property array<int, string>|null $present_persons
 * @property int $recorded_by
 * @property array<int, int>|null $attachment_file_ids
 */
class CaseEntry extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<CaseEntryFactory> */
    use HasFactory;

    use HasUlid;

    public $timestamps = false;

    protected $fillable = [
        'school_id', 'case_id', 'entry_type', 'entry_at', 'recorded_at', 'content', 'is_learner_account',
        'present_persons', 'recorded_by', 'attachment_file_ids',
    ];

    protected function casts(): array
    {
        return [
            'entry_at' => 'datetime',
            'recorded_at' => 'datetime',
            'content' => SecondaryEncrypted::class,
            'is_learner_account' => 'boolean',
            'present_persons' => 'array',
            'attachment_file_ids' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (): never {
            throw new InvalidStateTransitionException('case_entries is append-only and can never be updated (BR-BRD-08-008).');
        });

        static::deleting(function (): never {
            throw new InvalidStateTransitionException('case_entries is append-only and can never be deleted (BR-BRD-08-008).');
        });
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return CaseEntryFactory::new();
    }

    /**
     * @return BelongsTo<SafeguardingCase, $this>
     */
    public function case(): BelongsTo
    {
        return $this->belongsTo(SafeguardingCase::class, 'case_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
