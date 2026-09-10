<?php

declare(strict_types=1);

namespace Modules\Core\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Book A CORE-09 §2/BR-CORE-09-011.
 *
 * @property int $id
 * @property int $school_id
 * @property string $address
 * @property string $channel
 * @property string|null $reason
 * @property Carbon $opted_out_at
 */
class NotificationOptOut extends Model
{
    public $timestamps = false;

    protected $fillable = ['school_id', 'address', 'channel', 'reason', 'opted_out_at'];

    protected function casts(): array
    {
        return [
            'opted_out_at' => 'datetime',
        ];
    }
}
