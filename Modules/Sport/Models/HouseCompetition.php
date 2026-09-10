<?php

declare(strict_types=1);

namespace Modules\Sport\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Modules\Core\Domain\Concerns\BelongsToSchool;
use Modules\Core\Domain\Concerns\HasUlid;
use Modules\Sport\Database\Factories\HouseCompetitionFactory;

/**
 * Book H2 OPS-07 §2.
 *
 * @property int $id
 * @property string $ulid
 * @property int $school_id
 * @property int $academic_year_id
 * @property string $name
 * @property string $competition_type
 * @property Carbon|null $held_on
 * @property array<int, int> $points_scheme place => points — JSON-object keys that are pure digits are coerced to int array keys by PHP itself, so this is genuinely int-keyed at runtime, never string-keyed
 * @property float $weight
 * @property string $status
 */
class HouseCompetition extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<HouseCompetitionFactory> */
    use HasFactory;

    use HasUlid;

    public $timestamps = false;

    protected $fillable = [
        'school_id', 'academic_year_id', 'name', 'competition_type', 'held_on',
        'points_scheme', 'weight', 'status',
    ];

    protected function casts(): array
    {
        return [
            'held_on' => 'date',
            'points_scheme' => 'array',
            'weight' => 'decimal:2',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return HouseCompetitionFactory::new();
    }
}
