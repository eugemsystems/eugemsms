<?php

declare(strict_types=1);

namespace Modules\Intelligence\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Modules\Intelligence\Database\Factories\RiskIndicatorFactory;

/**
 * Book J INT-03 §2/BR-INT-03-003. A materialization of
 * `Modules\Intelligence\Domain\Registry\RiskIndicatorRegistry`.
 *
 * @property int $id
 * @property string $key
 * @property string $module_code
 * @property string $applies_to
 * @property string $plain_language_description
 * @property float $default_weight
 */
class RiskIndicator extends Model
{
    /** @use HasFactory<RiskIndicatorFactory> */
    use HasFactory;

    protected $fillable = [
        'key', 'module_code', 'applies_to', 'plain_language_description', 'default_weight',
    ];

    protected function casts(): array
    {
        return [
            'default_weight' => 'float',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return RiskIndicatorFactory::new();
    }
}
