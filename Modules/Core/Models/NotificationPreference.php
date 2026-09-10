<?php

declare(strict_types=1);

namespace Modules\Core\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Book A CORE-09 §2.
 *
 * @property int $id
 * @property int $school_id
 * @property int $user_id
 * @property string|null $notification_key
 * @property string $channel
 * @property bool $is_enabled
 */
class NotificationPreference extends Model
{
    public $timestamps = false;

    protected $fillable = ['school_id', 'user_id', 'notification_key', 'channel', 'is_enabled'];

    protected function casts(): array
    {
        return [
            'is_enabled' => 'boolean',
        ];
    }
}
