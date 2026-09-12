<?php

declare(strict_types=1);

namespace Modules\Core\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Laravel's own `failed_jobs` table (see `config/queue.php`'s
 * `failed.database` driver) — a plain read/delete-oriented Eloquent
 * wrapper so it can be browsed through the same `<x-data-table>`/
 * `InteractsWithDataTable` machinery every other admin list screen
 * uses, rather than a bespoke pagination path for this one table.
 * Laravel itself ships no Eloquent model for this table. Retrying still
 * goes through the real `queue:retry` artisan command — this model is
 * for listing/deleting, never for constructing a job to dispatch.
 *
 * @property int $id
 * @property string $uuid
 * @property string $connection
 * @property string $queue
 * @property string $payload
 * @property string $exception
 * @property Carbon $failed_at
 */
class FailedJob extends Model
{
    protected $table = 'failed_jobs';

    public $timestamps = false;

    protected $guarded = ['*'];

    protected function casts(): array
    {
        return [
            'failed_at' => 'datetime',
        ];
    }

    public function displayName(): string
    {
        $payload = json_decode($this->payload, true);

        return $payload['displayName'] ?? 'Unknown job';
    }

    public function exceptionSummary(): string
    {
        $firstLine = strtok($this->exception, "\n");

        return $firstLine !== false ? $firstLine : $this->exception;
    }
}
