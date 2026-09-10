<?php

declare(strict_types=1);

namespace Modules\Security\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\Core\Domain\Concerns\BelongsToSchool;
use Modules\Core\Domain\Concerns\HasUlid;
use Modules\Security\Database\Factories\EmergencyDrillFactory;

/**
 * Book H2 OPS-06 §2/§3 ⭐/BR-OPS-06-008/009/010.
 *
 * @property int $id
 * @property string $ulid
 * @property int $school_id
 * @property int $term_id
 * @property string $drill_type
 * @property Carbon $conducted_at
 * @property bool $is_announced
 * @property int $expected_headcount
 * @property int|null $mustered_headcount
 * @property int|null $unaccounted_count
 * @property int|null $evacuation_seconds
 * @property array<int, mixed>|null $assembly_points
 * @property string|null $findings
 * @property string|null $actions_required
 * @property int $conducted_by
 */
class EmergencyDrill extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<EmergencyDrillFactory> */
    use HasFactory;

    use HasUlid;

    public $timestamps = false;

    protected $fillable = [
        'school_id', 'term_id', 'drill_type', 'conducted_at', 'is_announced', 'expected_headcount',
        'mustered_headcount', 'unaccounted_count', 'evacuation_seconds', 'assembly_points', 'findings',
        'actions_required', 'conducted_by',
    ];

    protected function casts(): array
    {
        return [
            'conducted_at' => 'datetime',
            'is_announced' => 'boolean',
            'assembly_points' => 'array',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return EmergencyDrillFactory::new();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function conductedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'conducted_by');
    }
}
