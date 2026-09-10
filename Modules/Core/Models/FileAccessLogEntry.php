<?php

declare(strict_types=1);

namespace Modules\Core\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Book A CORE-10 §2/BR-CORE-10-007.
 *
 * @property int $id
 * @property int $file_id
 * @property int $user_id
 * @property string $action
 * @property string|null $ip_address
 * @property Carbon $accessed_at
 */
class FileAccessLogEntry extends Model
{
    protected $table = 'file_access_log';

    public $timestamps = false;

    protected $fillable = ['file_id', 'user_id', 'action', 'ip_address', 'accessed_at'];

    protected function casts(): array
    {
        return [
            'accessed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<File, $this>
     */
    public function file(): BelongsTo
    {
        return $this->belongsTo(File::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
