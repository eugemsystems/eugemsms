<?php

declare(strict_types=1);

namespace Modules\Finance\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Finance\Database\Factories\CreditNoteLineFactory;

/**
 * Book B FIN-03 §2. No `school_id` of its own — always reached
 * through its owning `CreditNote` (same shape as `FeeStructureRule`).
 *
 * @property int $id
 * @property int $credit_note_id
 * @property int|null $invoice_line_id
 * @property int $component_id
 * @property string $description
 * @property int $amount_minor
 * @property string $currency
 */
class CreditNoteLine extends Model
{
    /** @use HasFactory<CreditNoteLineFactory> */
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'credit_note_id', 'invoice_line_id', 'component_id', 'description', 'amount_minor', 'currency',
    ];

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return CreditNoteLineFactory::new();
    }

    /**
     * @return BelongsTo<CreditNote, $this>
     */
    public function creditNote(): BelongsTo
    {
        return $this->belongsTo(CreditNote::class);
    }

    /**
     * @return BelongsTo<InvoiceLine, $this>
     */
    public function invoiceLine(): BelongsTo
    {
        return $this->belongsTo(InvoiceLine::class);
    }

    /**
     * @return BelongsTo<FeeComponent, $this>
     */
    public function component(): BelongsTo
    {
        return $this->belongsTo(FeeComponent::class);
    }
}
