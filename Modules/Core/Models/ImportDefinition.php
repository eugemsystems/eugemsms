<?php

declare(strict_types=1);

namespace Modules\Core\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Book A CORE-11 §2. Mirrors `ImporterRegistry`.
 *
 * @property int $id
 * @property string $key
 * @property string $label
 * @property string $module_code
 * @property string $importer_class
 * @property string|null $description
 * @property string $required_permission
 * @property array<int, string>|null $depends_on
 * @property bool $is_rollbackable
 * @property int $sort_order
 */
class ImportDefinition extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'key', 'label', 'module_code', 'importer_class', 'description',
        'required_permission', 'depends_on', 'is_rollbackable', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'depends_on' => 'array',
            'is_rollbackable' => 'boolean',
        ];
    }
}
