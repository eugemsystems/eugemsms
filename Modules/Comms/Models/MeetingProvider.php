<?php

declare(strict_types=1);

namespace Modules\Comms\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Modules\Comms\Database\Factories\MeetingProviderFactory;
use Modules\Core\Domain\Concerns\BelongsToSchool;

/**
 * Book I COM-07 §2.
 *
 * @property int $id
 * @property int $school_id
 * @property string $provider
 * @property string $credentials
 * @property string|null $account_email
 * @property string|null $webhook_secret
 * @property bool $is_active
 */
class MeetingProvider extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<MeetingProviderFactory> */
    use HasFactory;

    protected $fillable = [
        'school_id', 'provider', 'credentials', 'account_email', 'webhook_secret', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'credentials' => 'encrypted',
            'webhook_secret' => 'encrypted',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return MeetingProviderFactory::new();
    }
}
