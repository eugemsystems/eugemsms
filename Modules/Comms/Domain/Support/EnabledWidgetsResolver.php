<?php

declare(strict_types=1);

namespace Modules\Comms\Domain\Support;

use Illuminate\Support\Collection;
use Modules\Comms\Domain\DataObjects\WidgetDefinition;
use Modules\Comms\Domain\Registry\WidgetRegistry;
use Modules\Comms\Models\SchoolWidgetSetting;
use Modules\Core\Models\SchoolModule;

/**
 * Book I COM-03 §6/BR-COM-03-003/004. A widget is invisible, not
 * shown empty, when its module is disabled — filtered here, before
 * it ever reaches a settings screen to configure, matching
 * BR-COM-03-004's own "never sees an exeat tile to configure in the
 * first place" wording. `$gradeOrdinal === null` (a non-learner
 * persona) never filters on `min_grade_ordinal`.
 */
final class EnabledWidgetsResolver
{
    /**
     * @return array<int, WidgetDefinition>
     */
    public function resolve(string $persona, int $schoolId, ?int $gradeOrdinal = null): array
    {
        $widgets = WidgetRegistry::forPersona($persona);
        $settings = SchoolWidgetSetting::where('school_id', $schoolId)->where('persona', $persona)->get()->keyBy('widget_key');
        $enabledModules = SchoolModule::where('school_id', $schoolId)->where('is_enabled', true)->pluck('module_code');

        return (new Collection($widgets))
            ->filter(function (WidgetDefinition $widget) use ($settings, $enabledModules, $gradeOrdinal): bool {
                if ($widget->requiresModule !== null && ! $enabledModules->contains($widget->requiresModule)) {
                    return false;
                }

                if ($gradeOrdinal !== null && $widget->minGradeOrdinal !== null && $gradeOrdinal < $widget->minGradeOrdinal) {
                    return false;
                }

                $setting = $settings->get($widget->key);

                return $setting !== null ? $setting->is_enabled : $widget->defaultEnabled;
            })
            ->sortBy(function (WidgetDefinition $widget) use ($settings): int {
                $setting = $settings->get($widget->key);

                return $setting !== null && $setting->sort_order !== null ? $setting->sort_order : $widget->defaultSortOrder;
            })
            ->values()
            ->all();
    }
}
