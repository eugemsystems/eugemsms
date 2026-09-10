<?php

declare(strict_types=1);

namespace Modules\Core\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Modules\Core\Database\Factories\SchoolModuleFactory;
use Modules\Core\Domain\Concerns\BelongsToSchool;

/**
 * Per-school module entitlement (Volume 1 §5.3, Book A CORE-04).
 *
 * @property int $id
 * @property int $school_id
 * @property string $module_code
 * @property bool $is_enabled
 * @property Carbon|null $enabled_at
 * @property int|null $enabled_by
 * @property Carbon|null $expires_at
 * @property array<string, mixed>|null $config
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class SchoolModule extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<SchoolModuleFactory> */
    use HasFactory;

    protected $fillable = [
        'school_id',
        'module_code',
        'is_enabled',
        'enabled_at',
        'enabled_by',
        'expires_at',
        'config',
    ];

    protected function casts(): array
    {
        return [
            'is_enabled' => 'boolean',
            'enabled_at' => 'datetime',
            'expires_at' => 'datetime',
            'config' => 'array',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return SchoolModuleFactory::new();
    }

    public function isCurrentlyEnabled(): bool
    {
        if (! $this->is_enabled) {
            return false;
        }

        return $this->expires_at === null || $this->expires_at->isFuture();
    }
}
