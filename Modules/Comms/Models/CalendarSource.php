<?php

declare(strict_types=1);

namespace Modules\Comms\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Modules\Comms\Database\Factories\CalendarSourceFactory;

/**
 * Book I COM-06 §2/BR-COM-06-001. A materialization of
 * `Modules\Comms\Domain\Registry\CalendarSourceRegistry` — see that
 * class's own docblock.
 *
 * @property int $id
 * @property string $module_code
 * @property string $source_type
 * @property string|null $default_colour
 * @property string $default_audience_scope
 * @property bool $is_active
 */
class CalendarSource extends Model
{
    /** @use HasFactory<CalendarSourceFactory> */
    use HasFactory;

    protected $fillable = [
        'module_code', 'source_type', 'default_colour', 'default_audience_scope', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return CalendarSourceFactory::new();
    }
}
