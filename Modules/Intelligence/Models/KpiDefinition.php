<?php

declare(strict_types=1);

namespace Modules\Intelligence\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Modules\Intelligence\Database\Factories\KpiDefinitionFactory;

/**
 * Book J INT-02 §2/BR-INT-02-002. A materialization of
 * `Modules\Intelligence\Domain\Registry\KpiRegistry`.
 *
 * @property int $id
 * @property int|null $school_id
 * @property string $key
 * @property string $module_code
 * @property string $label
 * @property string $unit
 * @property string $data_source_endpoint
 * @property bool $higher_is_better
 * @property float|null $default_target_value
 */
class KpiDefinition extends Model
{
    /** @use HasFactory<KpiDefinitionFactory> */
    use HasFactory;

    protected $fillable = [
        'school_id', 'key', 'module_code', 'label', 'unit', 'data_source_endpoint',
        'higher_is_better', 'default_target_value',
    ];

    protected function casts(): array
    {
        return [
            'higher_is_better' => 'boolean',
            'default_target_value' => 'float',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return KpiDefinitionFactory::new();
    }
}
