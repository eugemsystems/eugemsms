<?php

declare(strict_types=1);

namespace Modules\Finance\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\Core\Domain\Concerns\BelongsToSchool;
use Modules\Core\Domain\Concerns\HasUlid;
use Modules\Core\Domain\Exceptions\InvalidStateTransitionException;
use Modules\Core\Domain\Support\PeriodGuard;
use Modules\Finance\Database\Factories\PaymentIntentFactory;
use Modules\People\Models\Student;

/**
 * Book B FIN-05 §3/BR-FIN-05-001/002 ⭐. Not a receipt — no journal
 * exists until a driver confirms settlement. `idempotency_key` is
 * globally unique, so repeating a client's key can only ever resolve
 * back to this same row (`InitiatePaymentAction` looks it up first).
 *
 * @property int $id
 * @property string $ulid
 * @property int $school_id
 * @property int $academic_year_id
 * @property int $term_id
 * @property int $gateway_id
 * @property string $reference
 * @property string $idempotency_key
 * @property int|null $student_id
 * @property int|null $payer_user_id
 * @property string $payer_name
 * @property string|null $payer_phone
 * @property string|null $payer_email
 * @property string $purpose
 * @property int $amount_minor
 * @property string $currency
 * @property string|null $method
 * @property string $status
 * @property string|null $gateway_reference
 * @property string|null $poll_url
 * @property string|null $checkout_url
 * @property string|null $instructions
 * @property string|null $failure_code
 * @property string|null $failure_message
 * @property int|null $fee_minor
 * @property int|null $net_settled_minor
 * @property int|null $receipt_id
 * @property Carbon $initiated_at
 * @property Carbon|null $completed_at
 * @property Carbon $expires_at
 * @property int $poll_attempts
 * @property Carbon|null $last_polled_at
 * @property array<string, mixed>|null $metadata
 */
class PaymentIntent extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<PaymentIntentFactory> */
    use HasFactory;

    use HasUlid;

    private const array MUTABLE_AFTER_CREATE = [
        'status', 'method', 'gateway_reference', 'poll_url', 'checkout_url', 'instructions',
        'failure_code', 'failure_message', 'fee_minor', 'net_settled_minor', 'receipt_id',
        'completed_at', 'poll_attempts', 'last_polled_at', 'metadata',
    ];

    protected $fillable = [
        'school_id', 'academic_year_id', 'term_id', 'gateway_id', 'reference',
        'idempotency_key', 'student_id', 'payer_user_id', 'payer_name', 'payer_phone',
        'payer_email', 'purpose', 'amount_minor', 'currency', 'method', 'status',
        'gateway_reference', 'poll_url', 'checkout_url', 'instructions', 'failure_code',
        'failure_message', 'fee_minor', 'net_settled_minor', 'receipt_id', 'initiated_at',
        'completed_at', 'expires_at', 'poll_attempts', 'last_polled_at', 'metadata',
    ];

    protected function casts(): array
    {
        return [
            'amount_minor' => 'integer',
            'initiated_at' => 'datetime',
            'completed_at' => 'datetime',
            'expires_at' => 'datetime',
            'poll_attempts' => 'integer',
            'last_polled_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return PaymentIntentFactory::new();
    }

    protected static function booted(): void
    {
        static::creating(function (Model $model): void {
            PeriodGuard::assertWritable($model);
        });

        static::updating(function (Model $model): void {
            $dirty = array_keys($model->getDirty());
            $illegal = array_diff($dirty, self::MUTABLE_AFTER_CREATE);

            if ($illegal !== []) {
                throw new InvalidStateTransitionException(
                    'A payment_intents row may only change its status/result columns after creation.',
                    ['dirty' => $illegal],
                );
            }
        });
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    /**
     * @return BelongsTo<PaymentGateway, $this>
     */
    public function gateway(): BelongsTo
    {
        return $this->belongsTo(PaymentGateway::class);
    }

    /**
     * @return BelongsTo<Student, $this>
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    /**
     * @return BelongsTo<Receipt, $this>
     */
    public function receipt(): BelongsTo
    {
        return $this->belongsTo(Receipt::class);
    }
}
