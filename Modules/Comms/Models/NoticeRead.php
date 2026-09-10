<?php

declare(strict_types=1);

namespace Modules\Comms\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\Comms\Database\Factories\NoticeReadFactory;
use Modules\Core\Domain\Concerns\BelongsToSchool;
use Modules\Core\Domain\Exceptions\InvalidStateTransitionException;

/**
 * Book I COM-06 §2/BR-COM-06-004. A read receipt, once written, never
 * changes — see the owning migration's docblock. `$timestamps = false`:
 * `read_at` IS the timestamp, there is no separate `created_at`/
 * `updated_at` to track on a row that can never legitimately update.
 *
 * @property int $id
 * @property int $school_id
 * @property int $notice_id
 * @property int $user_id
 * @property Carbon $read_at
 */
class NoticeRead extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<NoticeReadFactory> */
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'school_id', 'notice_id', 'user_id', 'read_at',
    ];

    protected function casts(): array
    {
        return [
            'read_at' => 'datetime',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return NoticeReadFactory::new();
    }

    protected static function booted(): void
    {
        static::updating(function (Model $model): void {
            throw new InvalidStateTransitionException(
                'A notice_reads row is append-only and may never be updated.',
                ['dirty' => array_keys($model->getDirty())],
            );
        });
    }

    /**
     * @return BelongsTo<Notice, $this>
     */
    public function notice(): BelongsTo
    {
        return $this->belongsTo(Notice::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
