<?php

declare(strict_types=1);

namespace Modules\Security\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\Core\Domain\Concerns\BelongsToSchool;
use Modules\Core\Domain\Concerns\HasUlid;
use Modules\Core\Domain\Exceptions\InvalidStateTransitionException;
use Modules\Security\Database\Factories\OccurrenceBookEntryFactory;

/**
 * Book H2 OPS-06 §2 ⭐/BR-OPS-06-003/012 — APPEND-ONLY, gapless
 * `entry_number` per school.
 *
 * @property int $id
 * @property string $ulid
 * @property int $school_id
 * @property int $entry_number
 * @property Carbon $occurred_at
 * @property Carbon $recorded_at
 * @property string|null $shift
 * @property string $category
 * @property string $description
 * @property string|null $location
 * @property string|null $persons_involved
 * @property string|null $action_taken
 * @property int|null $escalated_to
 * @property string|null $cctv_reference
 * @property array<int, int>|null $photo_file_ids
 * @property int $recorded_by
 * @property int|null $corrects_entry_id
 */
class OccurrenceBookEntry extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<OccurrenceBookEntryFactory> */
    use HasFactory;

    use HasUlid;

    protected $table = 'occurrence_book';

    public $timestamps = false;

    protected $fillable = [
        'school_id', 'entry_number', 'occurred_at', 'recorded_at', 'shift', 'category', 'description',
        'location', 'persons_involved', 'action_taken', 'escalated_to', 'cctv_reference', 'photo_file_ids',
        'recorded_by', 'corrects_entry_id',
    ];

    protected function casts(): array
    {
        return [
            'occurred_at' => 'datetime',
            'recorded_at' => 'datetime',
            'photo_file_ids' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (): never {
            throw new InvalidStateTransitionException('occurrence_book is append-only and can never be updated (BR-OPS-06-003). Record a new entry referencing this one instead.');
        });

        static::deleting(function (): never {
            throw new InvalidStateTransitionException('occurrence_book is append-only and can never be deleted (BR-OPS-06-003).');
        });
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return OccurrenceBookEntryFactory::new();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function escalatedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'escalated_to');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    /**
     * @return BelongsTo<self, $this>
     */
    public function correctsEntry(): BelongsTo
    {
        return $this->belongsTo(self::class, 'corrects_entry_id');
    }
}
