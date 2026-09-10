<?php

declare(strict_types=1);

namespace Modules\Core\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Book A CORE-08 §2/BR-CORE-08-009.
 *
 * @property int $id
 * @property int $school_id
 * @property int $user_id
 * @property string $access_type
 * @property string $resource_type
 * @property int|null $resource_id
 * @property int|null $record_count
 * @property string|null $purpose
 * @property string|null $ip_address
 * @property Carbon $accessed_at
 */
class DataAccessLogEntry extends Model
{
    protected $table = 'data_access_log';

    public $timestamps = false;

    protected $fillable = [
        'school_id', 'user_id', 'access_type', 'resource_type', 'resource_id',
        'record_count', 'purpose', 'ip_address', 'accessed_at',
    ];

    protected function casts(): array
    {
        return [
            'accessed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
