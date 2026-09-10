<?php

declare(strict_types=1);

namespace Modules\Finance\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Finance\Database\Factories\ExchangeRateSourceFactory;

/**
 * Book B FIN-06 §2. `school_id = null` means a system-wide source
 * (e.g. an RBZ interbank feed shared by every tenant).
 *
 * @property int $id
 * @property int|null $school_id
 * @property string $key
 * @property string $name
 * @property bool $is_automatic
 * @property string|null $endpoint_url
 * @property bool $requires_approval
 * @property int $priority
 * @property bool $is_active
 */
class ExchangeRateSource extends Model
{
    /** @use HasFactory<ExchangeRateSourceFactory> */
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'school_id', 'key', 'name', 'is_automatic', 'endpoint_url', 'requires_approval', 'priority', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_automatic' => 'boolean',
            'requires_approval' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return ExchangeRateSourceFactory::new();
    }

    /**
     * @return HasMany<ExchangeRate, $this>
     */
    public function rates(): HasMany
    {
        return $this->hasMany(ExchangeRate::class, 'source_id');
    }
}
