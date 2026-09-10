<?php

declare(strict_types=1);

namespace Modules\Stores\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\Core\Domain\Concerns\BelongsToSchool;
use Modules\Core\Domain\Concerns\HasUlid;
use Modules\Core\Models\AcademicYear;
use Modules\Stores\Database\Factories\ForecastFactory;

/**
 * Book H1 FIN-11 §2/BR-FIN-11-013/014/015 ⭐ — a scenario, never a
 * plan. Nothing in this module ever reads a `Forecast` row to decide
 * what a `Budget`/`BudgetLine` should contain.
 *
 * @property int $id
 * @property string $ulid
 * @property int $school_id
 * @property int $academic_year_id
 * @property string $forecast_type
 * @property string $scenario_name
 * @property array<string, mixed> $assumptions
 * @property array<string, mixed> $projections
 * @property Carbon $generated_at
 * @property int $generated_by
 * @property bool $is_baseline
 */
class Forecast extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<ForecastFactory> */
    use HasFactory;

    use HasUlid;

    public $timestamps = false;

    protected $fillable = [
        'school_id', 'academic_year_id', 'forecast_type', 'scenario_name', 'assumptions', 'projections',
        'generated_at', 'generated_by', 'is_baseline',
    ];

    protected function casts(): array
    {
        return [
            'assumptions' => 'array',
            'projections' => 'array',
            'generated_at' => 'datetime',
            'is_baseline' => 'boolean',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return ForecastFactory::new();
    }

    /**
     * @return BelongsTo<AcademicYear, $this>
     */
    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function generatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'generated_by');
    }
}
