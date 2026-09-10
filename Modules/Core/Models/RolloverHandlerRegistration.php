<?php

declare(strict_types=1);

namespace Modules\Core\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Book A CORE-03 §5 — a queryable mirror of the in-code
 * `RolloverHandlerRegistry`, kept in sync by
 * `RolloverHandlerRegistry::syncToDatabase()`. Global, not tenant data:
 * which handlers exist is a property of the installed codebase, not any
 * one school, so this deliberately does not use `BelongsToSchool`.
 *
 * @property int $id
 * @property string $module_code
 * @property string $handler_class
 * @property int $sort_order
 * @property bool $is_blocking
 * @property string|null $description
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class RolloverHandlerRegistration extends Model
{
    protected $table = 'rollover_handlers';

    protected $fillable = [
        'module_code',
        'handler_class',
        'sort_order',
        'is_blocking',
        'description',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'is_blocking' => 'boolean',
        ];
    }
}
