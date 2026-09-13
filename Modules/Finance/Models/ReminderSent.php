<?php

declare(strict_types=1);

namespace Modules\Finance\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\Core\Domain\Concerns\BelongsToSchool;
use Modules\Core\Domain\Exceptions\InvalidStateTransitionException;
use Modules\Finance\Database\Factories\ReminderSentFactory;

/**
 * Book B FIN-03 §2/BR-FIN-03-015. Append-only — `UNIQUE(schedule_id,
 * invoice_id)` on the migration is the whole duplicate-suppression
 * mechanism (AC-FIN-03-005).
 *
 * @property int $id
 * @property int $school_id
 * @property int $schedule_id
 * @property int $invoice_id
 * @property int|null $notification_id
 * @property int $balance_at_send_minor
 * @property Carbon $sent_at
 */
class ReminderSent extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<ReminderSentFactory> */
    use HasFactory;

    // Eloquent's pluralizer guesses `reminder_sents` from this class
    // name — the migration (matching the spec's own table name) is
    // `reminders_sent`.
    protected $table = 'reminders_sent';

    public $timestamps = false;

    protected $fillable = [
        'school_id', 'schedule_id', 'invoice_id', 'notification_id',
        'balance_at_send_minor', 'sent_at',
    ];

    protected function casts(): array
    {
        return [
            'sent_at' => 'datetime',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return ReminderSentFactory::new();
    }

    protected static function booted(): void
    {
        static::updating(function (): never {
            throw new InvalidStateTransitionException('reminders_sent is append-only and can never be updated.');
        });

        static::deleting(function (): never {
            throw new InvalidStateTransitionException('reminders_sent is append-only and can never be deleted.');
        });
    }

    /**
     * @return BelongsTo<ReminderSchedule, $this>
     */
    public function schedule(): BelongsTo
    {
        return $this->belongsTo(ReminderSchedule::class, 'schedule_id');
    }

    /**
     * @return BelongsTo<Invoice, $this>
     */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }
}
