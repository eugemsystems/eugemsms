<?php

declare(strict_types=1);

namespace Modules\Comms\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Comms\Database\Factories\SchoolWidgetSettingFactory;
use Modules\Core\Domain\Concerns\BelongsToSchool;

/**
 * Book I COM-03 §2/BR-COM-03-004.
 *
 * @property int $id
 * @property int $school_id
 * @property string $widget_key
 * @property string $persona
 * @property bool $is_enabled
 * @property int|null $sort_order
 */
class SchoolWidgetSetting extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<SchoolWidgetSettingFactory> */
    use HasFactory;

    protected $fillable = [
        'school_id', 'widget_key', 'persona', 'is_enabled', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_enabled' => 'boolean',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return SchoolWidgetSettingFactory::new();
    }

    /**
     * @return BelongsTo<DashboardWidget, $this>
     */
    public function widget(): BelongsTo
    {
        return $this->belongsTo(DashboardWidget::class, 'widget_key', 'key');
    }
}
