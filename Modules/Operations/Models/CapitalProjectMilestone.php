<?php

declare(strict_types=1);

namespace Modules\Operations\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\Core\Domain\Concerns\BelongsToSchool;
use Modules\Operations\Database\Factories\CapitalProjectMilestoneFactory;

/**
 * @property int $id
 * @property int $school_id
 * @property int $project_id
 * @property int $sequence
 * @property string $name
 * @property Carbon $target_date
 * @property Carbon|null $completed_date
 * @property float|null $payment_percent
 * @property string $status
 */
class CapitalProjectMilestone extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<CapitalProjectMilestoneFactory> */
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'school_id', 'project_id', 'sequence', 'name', 'target_date', 'completed_date', 'payment_percent',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'target_date' => 'date',
            'completed_date' => 'date',
            'payment_percent' => 'decimal:2',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return CapitalProjectMilestoneFactory::new();
    }

    /**
     * @return BelongsTo<CapitalProject, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(CapitalProject::class, 'project_id');
    }
}
