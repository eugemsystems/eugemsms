<?php

declare(strict_types=1);

namespace Modules\Finance\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Core\Domain\Concerns\BelongsToSchool;
use Modules\Core\Domain\Exceptions\InvalidStateTransitionException;
use Modules\Finance\Database\Factories\InvoiceLineFactory;

/**
 * Book B FIN-03 §2. Immutable once written, same reasoning as
 * `LearnerFeeLine` — a correction is a credit note, never an edit.
 *
 * @property int $id
 * @property int $school_id
 * @property int $invoice_id
 * @property int $line_number
 * @property int $component_id
 * @property int|null $fee_line_id
 * @property string $description
 * @property string|null $calculation_note
 * @property string $quantity
 * @property int|null $unit_rate_minor
 * @property int $gross_minor
 * @property int $discount_minor
 * @property int $net_minor
 * @property string $currency
 * @property int $allocation_priority
 * @property string $tax_category
 * @property bool $is_fiscalisable
 */
class InvoiceLine extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<InvoiceLineFactory> */
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'school_id', 'invoice_id', 'line_number', 'component_id', 'fee_line_id',
        'description', 'calculation_note', 'quantity', 'unit_rate_minor', 'gross_minor',
        'discount_minor', 'net_minor', 'currency', 'allocation_priority', 'tax_category',
        'is_fiscalisable',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:4',
            'is_fiscalisable' => 'boolean',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return InvoiceLineFactory::new();
    }

    protected static function booted(): void
    {
        static::updating(function (): void {
            throw new InvalidStateTransitionException('invoice_lines is immutable once written — a correction is a credit note, never an edit.', []);
        });

        static::deleting(function (): void {
            throw new InvalidStateTransitionException('invoice_lines rows are never deleted.', []);
        });
    }

    /**
     * @return BelongsTo<Invoice, $this>
     */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    /**
     * @return BelongsTo<FeeComponent, $this>
     */
    public function component(): BelongsTo
    {
        return $this->belongsTo(FeeComponent::class);
    }

    /**
     * @return BelongsTo<LearnerFeeLine, $this>
     */
    public function feeLine(): BelongsTo
    {
        return $this->belongsTo(LearnerFeeLine::class, 'fee_line_id');
    }
}
