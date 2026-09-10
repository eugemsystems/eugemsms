<?php

declare(strict_types=1);

namespace Modules\Operations\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\Core\Domain\Concerns\BelongsToSchool;
use Modules\Core\Domain\Concerns\HasUlid;
use Modules\Operations\Database\Factories\FaultReportFactory;

/**
 * Book H2 OPS-02 §2/BR-OPS-02-001/002 ⭐.
 *
 * @property int $id
 * @property string $ulid
 * @property int $school_id
 * @property int $term_id
 * @property string $report_number
 * @property int|null $maintenance_asset_id
 * @property string $location
 * @property string $category
 * @property string $description
 * @property string $severity
 * @property bool $affects_safety
 * @property bool $affects_teaching
 * @property int $reported_by
 * @property Carbon $reported_at
 * @property string|null $source_type
 * @property int|null $source_id
 * @property string $status
 * @property int|null $work_order_id
 * @property int|null $triaged_by
 * @property string|null $triage_note
 */
class FaultReport extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<FaultReportFactory> */
    use HasFactory;

    use HasUlid;

    public $timestamps = false;

    protected $fillable = [
        'school_id', 'term_id', 'report_number', 'maintenance_asset_id', 'location', 'category',
        'description', 'photo_file_ids', 'severity', 'affects_safety', 'affects_teaching', 'reported_by',
        'reported_at', 'source_type', 'source_id', 'status', 'work_order_id', 'triaged_by', 'triage_note',
    ];

    protected function casts(): array
    {
        return [
            'photo_file_ids' => 'array',
            'affects_safety' => 'boolean',
            'affects_teaching' => 'boolean',
            'reported_at' => 'datetime',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return FaultReportFactory::new();
    }

    /**
     * @return BelongsTo<MaintenanceAsset, $this>
     */
    public function maintenanceAsset(): BelongsTo
    {
        return $this->belongsTo(MaintenanceAsset::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function reportedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reported_by');
    }

    /**
     * @return BelongsTo<WorkOrder, $this>
     */
    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class);
    }
}
