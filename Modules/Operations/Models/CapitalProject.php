<?php

declare(strict_types=1);

namespace Modules\Operations\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Modules\Core\Domain\Concerns\BelongsToSchool;
use Modules\Core\Domain\Concerns\HasUlid;
use Modules\Operations\Database\Factories\CapitalProjectFactory;
use Modules\People\Models\Staff;
use Modules\Stores\Models\AssetCategory;
use Modules\Stores\Models\BudgetLine;
use Modules\Stores\Models\Supplier;

/**
 * Book H2 OPS-02 §2/BR-OPS-02-015.
 *
 * @property int $id
 * @property string $ulid
 * @property int $school_id
 * @property string $project_number
 * @property string $name
 * @property int $budget_minor
 * @property string $currency
 * @property int $committed_minor
 * @property int $spent_minor
 * @property int|null $budget_line_id
 * @property int|null $asset_category_id
 * @property Carbon $starts_on
 * @property Carbon|null $target_completion
 * @property Carbon|null $actual_completion
 * @property int|null $project_manager_id
 * @property int|null $main_contractor_id
 * @property string $status
 * @property bool $capitalise_on_completion
 */
class CapitalProject extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<CapitalProjectFactory> */
    use HasFactory;

    use HasUlid;

    public $timestamps = false;

    protected $fillable = [
        'school_id', 'project_number', 'name', 'description', 'budget_minor', 'currency', 'committed_minor',
        'spent_minor', 'budget_line_id', 'asset_category_id', 'starts_on', 'target_completion', 'actual_completion',
        'project_manager_id', 'main_contractor_id', 'status', 'capitalise_on_completion',
    ];

    protected function casts(): array
    {
        return [
            'starts_on' => 'date',
            'target_completion' => 'date',
            'actual_completion' => 'date',
            'capitalise_on_completion' => 'boolean',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return CapitalProjectFactory::new();
    }

    /**
     * @return BelongsTo<BudgetLine, $this>
     */
    public function budgetLine(): BelongsTo
    {
        return $this->belongsTo(BudgetLine::class);
    }

    /**
     * @return BelongsTo<AssetCategory, $this>
     */
    public function assetCategory(): BelongsTo
    {
        return $this->belongsTo(AssetCategory::class);
    }

    /**
     * @return BelongsTo<Staff, $this>
     */
    public function projectManager(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'project_manager_id');
    }

    /**
     * @return BelongsTo<Supplier, $this>
     */
    public function mainContractor(): BelongsTo
    {
        return $this->belongsTo(Supplier::class, 'main_contractor_id');
    }

    /**
     * @return HasMany<CapitalProjectMilestone, $this>
     */
    public function milestones(): HasMany
    {
        return $this->hasMany(CapitalProjectMilestone::class, 'project_id');
    }
}
