<?php

declare(strict_types=1);

namespace Modules\Core\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Modules\Core\Domain\Support\Install\InstallStepKey;
use Modules\Core\Domain\Support\Install\InstallStepStatus;

/**
 * Book A CORE-01 §2 — resumability. BR-CORE-01-003: a resumed
 * installation restarts at the first non-completed step, never step 1.
 *
 * @property int $id
 * @property InstallStepKey $step_key
 * @property InstallStepStatus $status
 * @property array<string, mixed>|null $payload
 * @property Carbon|null $completed_at
 * @property string|null $error_message
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class InstallStep extends Model
{
    protected $fillable = [
        'step_key',
        'status',
        'payload',
        'completed_at',
        'error_message',
    ];

    protected function casts(): array
    {
        return [
            'step_key' => InstallStepKey::class,
            'status' => InstallStepStatus::class,
            'payload' => 'array',
            'completed_at' => 'datetime',
        ];
    }
}
