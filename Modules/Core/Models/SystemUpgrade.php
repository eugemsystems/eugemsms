<?php

declare(strict_types=1);

namespace Modules\Core\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Modules\Core\Domain\Support\Install\UpgradeStatus;

/**
 * Book A CORE-01 §2.
 *
 * @property int $id
 * @property string $from_version
 * @property string $to_version
 * @property Carbon $started_at
 * @property Carbon|null $completed_at
 * @property UpgradeStatus $status
 * @property string|null $backup_reference
 * @property array<int, string>|null $migrations_run
 * @property string|null $error_log
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class SystemUpgrade extends Model
{
    protected $fillable = [
        'from_version',
        'to_version',
        'started_at',
        'completed_at',
        'status',
        'backup_reference',
        'migrations_run',
        'error_log',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'status' => UpgradeStatus::class,
            'migrations_run' => 'array',
        ];
    }
}
