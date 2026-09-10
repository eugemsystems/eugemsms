<?php

declare(strict_types=1);

namespace Modules\Compliance\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Modules\Compliance\Database\Factories\GovernanceMinuteFactory;
use Modules\Core\Domain\Concerns\BelongsToSchool;
use Modules\Core\Domain\Concerns\HasUlid;

/**
 * Book H3 CMP-04 §2/BR-CMP-04-007.
 *
 * @property int $id
 * @property string $ulid
 * @property int $school_id
 * @property string $body
 * @property Carbon $meeting_date
 * @property array<int, string> $attendees
 * @property int|null $minutes_file_id
 * @property array<int, string>|null $resolutions
 * @property string $confidentiality
 * @property array<int, int>|null $access_role_ids
 */
class GovernanceMinute extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<GovernanceMinuteFactory> */
    use HasFactory;

    use HasUlid;

    protected $fillable = [
        'school_id', 'body', 'meeting_date', 'attendees', 'minutes_file_id', 'resolutions',
        'confidentiality', 'access_role_ids',
    ];

    protected function casts(): array
    {
        return [
            'meeting_date' => 'date',
            'attendees' => 'array',
            'resolutions' => 'array',
            'access_role_ids' => 'array',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return GovernanceMinuteFactory::new();
    }

    /**
     * BR-CMP-04-007: restricted to named roles.
     *
     * @param  array<int, int>  $userRoleIds
     */
    public function isAccessibleTo(array $userRoleIds): bool
    {
        if ($this->confidentiality === 'open') {
            return true;
        }

        return count(array_intersect($this->access_role_ids ?? [], $userRoleIds)) > 0;
    }
}
