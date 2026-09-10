<?php

declare(strict_types=1);

namespace Modules\Core\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Book A CORE-12 §2/BR-CORE-12-010. Latest recorded reading per health
 * check key — one row is upserted per key on every health-check run, this
 * is not a historical log.
 *
 * @property int $id
 * @property string $check_key
 * @property string $status
 * @property string|null $value
 * @property string|null $threshold
 * @property string|null $message
 * @property Carbon $checked_at
 */
class SystemHealthCheck extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'check_key', 'status', 'value', 'threshold', 'message', 'checked_at',
    ];

    protected function casts(): array
    {
        return [
            'checked_at' => 'datetime',
        ];
    }

    public function isHealthy(): bool
    {
        return $this->status === 'ok';
    }
}
