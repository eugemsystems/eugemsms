<?php

declare(strict_types=1);

namespace Modules\Core\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Modules\Core\Database\Factories\SettingDefinitionFactory;

/**
 * Book A CORE-04 §2 — "registered in code, synced on deploy" by
 * `SettingDefinitionRegistry::syncToDatabase()`, the same split as
 * `rollover_handlers` (CORE-03).
 *
 * @property int $id
 * @property string $key
 * @property string $module_code
 * @property string $group_key
 * @property string $label
 * @property string|null $description
 * @property string $data_type
 * @property string|null $default_value
 * @property string|null $validation_rules
 * @property array<int, mixed>|null $options
 * @property string $ui_control
 * @property string $lowest_scope
 * @property bool $is_encrypted
 * @property string|null $is_locked_on_tier
 * @property int $sort_order
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class SettingDefinition extends Model
{
    /** @use HasFactory<SettingDefinitionFactory> */
    use HasFactory;

    protected $fillable = [
        'key',
        'module_code',
        'group_key',
        'label',
        'description',
        'data_type',
        'default_value',
        'validation_rules',
        'options',
        'ui_control',
        'lowest_scope',
        'is_encrypted',
        'is_locked_on_tier',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'options' => 'array',
            'is_encrypted' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return SettingDefinitionFactory::new();
    }
}
