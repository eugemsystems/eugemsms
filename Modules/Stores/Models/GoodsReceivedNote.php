<?php

declare(strict_types=1);

namespace Modules\Stores\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Modules\Core\Domain\Concerns\BelongsToSchool;
use Modules\Core\Domain\Concerns\HasUlid;
use Modules\Core\Domain\Support\PeriodGuard;
use Modules\Finance\Models\Journal;
use Modules\Stores\Database\Factories\GoodsReceivedNoteFactory;

/**
 * Book H1 FIN-08 §2/BR-FIN-08-012/013/014.
 *
 * @property int $id
 * @property string $ulid
 * @property int $school_id
 * @property int $term_id
 * @property string $grn_number
 * @property int $purchase_order_id
 * @property int $supplier_id
 * @property Carbon $received_on
 * @property int $received_by
 * @property int|null $store_id
 * @property bool $is_partial
 * @property bool $has_rejections
 * @property int $total_value_minor
 * @property string $currency
 * @property string $status
 * @property int|null $journal_id
 */
class GoodsReceivedNote extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<GoodsReceivedNoteFactory> */
    use HasFactory;

    use HasUlid;

    protected $fillable = [
        'school_id', 'term_id', 'grn_number', 'purchase_order_id', 'supplier_id', 'delivery_note_ref',
        'received_on', 'received_by', 'inspected_by', 'store_id', 'is_partial', 'has_rejections',
        'total_value_minor', 'currency', 'status', 'journal_id', 'photo_file_ids', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'received_on' => 'date',
            'is_partial' => 'boolean',
            'has_rejections' => 'boolean',
            'photo_file_ids' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $model): void {
            PeriodGuard::assertWritable($model);
        });
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return GoodsReceivedNoteFactory::new();
    }

    /**
     * @return BelongsTo<PurchaseOrder, $this>
     */
    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    /**
     * @return BelongsTo<Supplier, $this>
     */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function receivedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    /**
     * @return BelongsTo<Journal, $this>
     */
    public function journal(): BelongsTo
    {
        return $this->belongsTo(Journal::class);
    }

    /**
     * @return HasMany<GrnLine, $this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(GrnLine::class, 'grn_id');
    }
}
