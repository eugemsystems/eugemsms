<?php

declare(strict_types=1);

namespace Modules\Core\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Modules\Core\Database\Factories\NotificationTemplateFactory;
use Modules\Core\Domain\Concerns\HasUlid;

/**
 * Book A CORE-09 §2. `school_id` nullable — null is the system default
 * for a (key, channel, locale), overridable per school. Deliberately
 * not `BelongsToSchool`: every write supplies `school_id` (or null)
 * explicitly, same reasoning as `Modules\Core\Models\ActivityLogEntry`.
 *
 * @property int $id
 * @property string $ulid
 * @property int|null $school_id
 * @property string $key
 * @property string $channel
 * @property string $locale
 * @property string|null $subject
 * @property string $body
 * @property array<int, string>|null $variables
 * @property string|null $provider_template_id
 * @property bool $is_active
 */
class NotificationTemplate extends Model
{
    /** @use HasFactory<NotificationTemplateFactory> */
    use HasFactory;

    use HasUlid;

    protected $fillable = [
        'school_id', 'key', 'channel', 'locale', 'subject', 'body', 'variables',
        'provider_template_id', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'variables' => 'array',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return NotificationTemplateFactory::new();
    }
}
