<?php

declare(strict_types=1);

namespace Modules\Compliance\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Modules\Compliance\Database\Factories\DataQualityCheckFactory;
use Modules\Core\Domain\Concerns\BelongsToSchool;

/**
 * Book H3 CMP-02 §2/BR-CMP-02-002. One row per `check_key` per school
 * — `RunDataQualityChecksAction` refreshes it in place rather than
 * appending a history.
 *
 * @property int $id
 * @property int $school_id
 * @property string $check_key
 * @property string $entity_type
 * @property int $affected_count
 * @property array<int, int>|null $affected_ids
 * @property string $severity
 * @property Carbon $last_checked_at
 */
class DataQualityCheck extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<DataQualityCheckFactory> */
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'school_id', 'check_key', 'entity_type', 'affected_count', 'affected_ids', 'severity', 'last_checked_at',
    ];

    protected function casts(): array
    {
        return [
            'affected_ids' => 'array',
            'last_checked_at' => 'datetime',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return DataQualityCheckFactory::new();
    }
}
