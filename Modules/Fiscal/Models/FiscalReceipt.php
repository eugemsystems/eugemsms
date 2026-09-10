<?php

declare(strict_types=1);

namespace Modules\Fiscal\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\Core\Domain\Concerns\BelongsToSchool;
use Modules\Core\Domain\Concerns\HasUlid;
use Modules\Fiscal\Database\Factories\FiscalReceiptFactory;

/**
 * Book H3 FIN-13 §3/BR-FIN-13-001/004/005/009.
 *
 * @property int $id
 * @property string $ulid
 * @property int $school_id
 * @property int $device_id
 * @property int|null $fiscal_day_id
 * @property string $source_type
 * @property int $source_id
 * @property string $receipt_type
 * @property string $receipt_currency
 * @property int $receipt_counter
 * @property int $global_counter
 * @property string $invoice_number
 * @property Carbon $receipt_date
 * @property int $total_minor
 * @property array<string, mixed> $tax_breakdown
 * @property array<string, mixed> $payment_methods
 * @property string|null $buyer_name
 * @property string|null $buyer_tin
 * @property string|null $buyer_registration
 * @property string|null $buyer_address
 * @property int|null $credited_receipt_id
 * @property string|null $credit_reason
 * @property string|null $receipt_hash
 * @property string|null $receipt_signature
 * @property string|null $previous_receipt_hash
 * @property string|null $verification_code
 * @property string|null $qr_url
 * @property string|null $fdms_receipt_id
 * @property string $status
 * @property Carbon|null $submitted_at
 * @property Carbon|null $accepted_at
 * @property int $attempt_count
 * @property Carbon|null $last_attempt_at
 * @property string|null $error_code
 * @property string|null $error_message
 * @property array<string, mixed> $payload
 * @property array<string, mixed>|null $response
 */
class FiscalReceipt extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<FiscalReceiptFactory> */
    use HasFactory;

    use HasUlid;

    public $timestamps = false;

    protected $fillable = [
        'school_id', 'device_id', 'fiscal_day_id', 'source_type', 'source_id', 'receipt_type',
        'receipt_currency', 'receipt_counter', 'global_counter', 'invoice_number', 'receipt_date',
        'total_minor', 'tax_breakdown', 'payment_methods', 'buyer_name', 'buyer_tin', 'buyer_registration',
        'buyer_address', 'credited_receipt_id', 'credit_reason', 'receipt_hash', 'receipt_signature',
        'previous_receipt_hash', 'verification_code', 'qr_url', 'fdms_receipt_id', 'status', 'submitted_at',
        'accepted_at', 'attempt_count', 'last_attempt_at', 'error_code', 'error_message', 'payload', 'response',
    ];

    protected function casts(): array
    {
        return [
            'receipt_date' => 'datetime',
            'tax_breakdown' => 'array',
            'payment_methods' => 'array',
            'buyer_tin' => 'encrypted',
            'submitted_at' => 'datetime',
            'accepted_at' => 'datetime',
            'last_attempt_at' => 'datetime',
            'payload' => 'array',
            'response' => 'array',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return FiscalReceiptFactory::new();
    }

    /**
     * @return BelongsTo<FiscalDevice, $this>
     */
    public function device(): BelongsTo
    {
        return $this->belongsTo(FiscalDevice::class);
    }

    /**
     * @return BelongsTo<FiscalDay, $this>
     */
    public function fiscalDay(): BelongsTo
    {
        return $this->belongsTo(FiscalDay::class);
    }

    /**
     * @return BelongsTo<self, $this>
     */
    public function creditedReceipt(): BelongsTo
    {
        return $this->belongsTo(self::class, 'credited_receipt_id');
    }
}
