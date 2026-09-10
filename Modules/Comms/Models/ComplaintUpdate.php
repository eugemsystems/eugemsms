<?php

declare(strict_types=1);

namespace Modules\Comms\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\Comms\Database\Factories\ComplaintUpdateFactory;
use Modules\Core\Domain\Concerns\BelongsToSchool;
use Modules\Core\Domain\Exceptions\InvalidStateTransitionException;

/**
 * Book I COM-08 §2 ⭐/BR-COM-08-005 (AC-COM-08-003). Append-only —
 * once posted, an update's content and its `visible_to_raiser`
 * boundary never change; the server-side enforcement of THAT
 * boundary lives in `Modules\Comms\Domain\Actions\GetComplaintThreadForRaiserAction`,
 * not here — this model only guarantees the flag itself is immutable.
 *
 * @property int $id
 * @property int $school_id
 * @property int $complaint_id
 * @property string $update_type
 * @property string|null $content
 * @property bool $visible_to_raiser
 * @property int $posted_by
 * @property Carbon $posted_at
 */
class ComplaintUpdate extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<ComplaintUpdateFactory> */
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'school_id', 'complaint_id', 'update_type', 'content', 'visible_to_raiser', 'posted_by', 'posted_at',
    ];

    protected function casts(): array
    {
        return [
            'visible_to_raiser' => 'boolean',
            'posted_at' => 'datetime',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return ComplaintUpdateFactory::new();
    }

    protected static function booted(): void
    {
        static::updating(function (Model $model): void {
            throw new InvalidStateTransitionException(
                'A complaint_updates row is append-only and may never be updated.',
                ['dirty' => array_keys($model->getDirty())],
            );
        });
    }

    /**
     * @return BelongsTo<Complaint, $this>
     */
    public function complaint(): BelongsTo
    {
        return $this->belongsTo(Complaint::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function postedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'posted_by');
    }
}
