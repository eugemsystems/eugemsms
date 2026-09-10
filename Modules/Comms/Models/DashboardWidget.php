<?php

declare(strict_types=1);

namespace Modules\Comms\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Modules\Comms\Database\Factories\DashboardWidgetFactory;

/**
 * Book I COM-03 §2/BR-COM-03-001/003/004. A materialization of
 * `Modules\Comms\Domain\Registry\WidgetRegistry` — see that class and
 * the migration's own docblock.
 *
 * @property int $id
 * @property string $key
 * @property string $module_code
 * @property string $persona
 * @property string $title
 * @property string $data_endpoint
 * @property int|null $min_grade_ordinal
 * @property string|null $requires_module
 * @property bool $default_enabled
 * @property int $default_sort_order
 */
class DashboardWidget extends Model
{
    /** @use HasFactory<DashboardWidgetFactory> */
    use HasFactory;

    protected $fillable = [
        'key', 'module_code', 'persona', 'title', 'data_endpoint', 'min_grade_ordinal',
        'requires_module', 'default_enabled', 'default_sort_order',
    ];

    protected function casts(): array
    {
        return [
            'default_enabled' => 'boolean',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return DashboardWidgetFactory::new();
    }
}
