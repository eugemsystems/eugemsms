<?php

declare(strict_types=1);

namespace Modules\Utilities\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\Core\Domain\Concerns\BelongsToSchool;
use Modules\Core\Domain\Concerns\HasUlid;
use Modules\Finance\Models\Journal;
use Modules\Stores\Models\StoreRequisition;
use Modules\Utilities\Database\Factories\GeneratorRunFactory;

/**
 * Book H2 OPS-04 §2 ⭐/BR-OPS-04-011/012/013.
 *
 * @property int $id
 * @property string $ulid
 * @property int $school_id
 * @property int $term_id
 * @property int $generator_id
 * @property Carbon $run_date
 * @property Carbon $started_at
 * @property Carbon|null $stopped_at
 * @property float|null $hours_run
 * @property float $start_hour_meter
 * @property float|null $end_hour_meter
 * @property string $reason
 * @property string|null $load_shedding_stage
 * @property float|null $diesel_litres
 * @property int|null $diesel_cost_minor
 * @property string|null $currency
 * @property float|null $litres_per_hour
 * @property bool $is_anomaly
 * @property int|null $store_requisition_id
 * @property int|null $operated_by
 * @property int|null $journal_id
 */
class GeneratorRun extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<GeneratorRunFactory> */
    use HasFactory;

    use HasUlid;

    public $timestamps = false;

    protected $fillable = [
        'school_id', 'term_id', 'generator_id', 'run_date', 'started_at', 'stopped_at', 'hours_run',
        'start_hour_meter', 'end_hour_meter', 'reason', 'load_shedding_stage', 'diesel_litres',
        'diesel_cost_minor', 'currency', 'litres_per_hour', 'is_anomaly', 'store_requisition_id',
        'operated_by', 'journal_id',
    ];

    protected function casts(): array
    {
        return [
            'run_date' => 'date',
            'started_at' => 'datetime',
            'stopped_at' => 'datetime',
            'hours_run' => 'decimal:2',
            'start_hour_meter' => 'decimal:2',
            'end_hour_meter' => 'decimal:2',
            'diesel_litres' => 'decimal:2',
            'litres_per_hour' => 'decimal:3',
            'is_anomaly' => 'boolean',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return GeneratorRunFactory::new();
    }

    /**
     * @return BelongsTo<Generator, $this>
     */
    public function generator(): BelongsTo
    {
        return $this->belongsTo(Generator::class);
    }

    /**
     * @return BelongsTo<StoreRequisition, $this>
     */
    public function storeRequisition(): BelongsTo
    {
        return $this->belongsTo(StoreRequisition::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function operatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'operated_by');
    }

    /**
     * @return BelongsTo<Journal, $this>
     */
    public function journal(): BelongsTo
    {
        return $this->belongsTo(Journal::class);
    }
}
