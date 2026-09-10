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
use Modules\Fiscal\Database\Factories\FiscalZReportFactory;

/**
 * Book H3 FIN-13 §3/BR-FIN-13-018.
 *
 * @property int $id
 * @property string $ulid
 * @property int $school_id
 * @property int $device_id
 * @property int $fiscal_day_id
 * @property Carbon $report_date
 * @property int $receipt_count
 * @property array<string, mixed> $totals_by_currency
 * @property array<string, mixed> $totals_by_tax_type
 * @property array<string, mixed> $totals_by_payment
 * @property Carbon|null $submitted_at
 * @property string $status
 * @property int|null $document_id
 */
class FiscalZReport extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<FiscalZReportFactory> */
    use HasFactory;

    use HasUlid;

    public $timestamps = false;

    protected $fillable = [
        'school_id', 'device_id', 'fiscal_day_id', 'report_date', 'receipt_count', 'totals_by_currency',
        'totals_by_tax_type', 'totals_by_payment', 'submitted_at', 'status', 'document_id',
    ];

    protected function casts(): array
    {
        return [
            'report_date' => 'date',
            'totals_by_currency' => 'array',
            'totals_by_tax_type' => 'array',
            'totals_by_payment' => 'array',
            'submitted_at' => 'datetime',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return FiscalZReportFactory::new();
    }

    /**
     * @return BelongsTo<FiscalDay, $this>
     */
    public function fiscalDay(): BelongsTo
    {
        return $this->belongsTo(FiscalDay::class);
    }
}
