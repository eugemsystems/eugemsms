<?php

declare(strict_types=1);

namespace Modules\Core\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Book A CORE-08 §2/§4.
 *
 * @property int $id
 * @property int|null $school_id
 * @property string $check_type
 * @property string $status
 * @property int|null $records_checked
 * @property int $failures_found
 * @property array<int, array<string, mixed>>|null $failure_details
 * @property int|null $duration_ms
 * @property Carbon $ran_at
 */
class IntegrityCheckRun extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'school_id', 'check_type', 'status', 'records_checked', 'failures_found',
        'failure_details', 'duration_ms', 'ran_at',
    ];

    protected function casts(): array
    {
        return [
            'failure_details' => 'array',
            'ran_at' => 'datetime',
        ];
    }

    public function passed(): bool
    {
        return $this->status === 'passed';
    }
}
