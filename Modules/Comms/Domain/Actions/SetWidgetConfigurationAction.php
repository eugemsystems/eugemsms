<?php

declare(strict_types=1);

namespace Modules\Comms\Domain\Actions;

use Modules\Comms\Domain\Events\WidgetConfigurationChanged;
use Modules\Comms\Models\SchoolWidgetSetting;
use Modules\Core\Domain\Actions\Action;

/**
 * ACT-SetWidgetConfiguration (Book I COM-03 §6/BR-COM-03-004). A
 * school-level enable/reorder — a missing row (no configuration yet)
 * always falls back to the widget's own registered default, never an
 * implicit disable.
 */
final class SetWidgetConfigurationAction extends Action
{
    public function execute(int $schoolId, string $widgetKey, string $persona, bool $isEnabled, ?int $sortOrder = null): SchoolWidgetSetting
    {
        return $this->transaction(function () use ($schoolId, $widgetKey, $persona, $isEnabled, $sortOrder): SchoolWidgetSetting {
            $setting = SchoolWidgetSetting::updateOrCreate(
                ['school_id' => $schoolId, 'widget_key' => $widgetKey, 'persona' => $persona],
                ['is_enabled' => $isEnabled, 'sort_order' => $sortOrder],
            );

            event(new WidgetConfigurationChanged($setting));

            return $setting;
        });
    }
}
