<?php

declare(strict_types=1);

namespace Modules\Intelligence\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\Core\Domain\Concerns\BelongsToSchool;
use Modules\Intelligence\Database\Factories\ApiUsageLogFactory;

/**
 * Book J INT-04 §2/BR-INT-04-011.
 *
 * @property int $id
 * @property int $school_id
 * @property int $client_id
 * @property string $endpoint
 * @property string $method
 * @property int $status_code
 * @property int|null $duration_ms
 * @property Carbon $occurred_at
 */
class ApiUsageLog extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<ApiUsageLogFactory> */
    use HasFactory;

    /**
     * The spec's table name is singular (`api_usage_log`), unlike
     * Eloquent's default pluralisation guess (`api_usage_logs`).
     */
    protected $table = 'api_usage_log';

    public $timestamps = false;

    protected $fillable = [
        'school_id', 'client_id', 'endpoint', 'method', 'status_code', 'duration_ms', 'occurred_at',
    ];

    protected function casts(): array
    {
        return [
            'occurred_at' => 'datetime',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return ApiUsageLogFactory::new();
    }

    /**
     * @return BelongsTo<ApiClient, $this>
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(ApiClient::class, 'client_id');
    }
}
