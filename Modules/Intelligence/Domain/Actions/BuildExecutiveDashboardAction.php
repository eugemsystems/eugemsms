<?php

declare(strict_types=1);

namespace Modules\Intelligence\Domain\Actions;

use App\Models\User;
use Modules\Comms\Domain\DataObjects\WidgetResolverResult;
use Modules\Comms\Domain\Support\EnabledWidgetsResolver;
use Modules\Core\Domain\Actions\Action;

/**
 * ACT-BuildExecutiveDashboard (Book J INT-02 §3 ⭐/BR-INT-02-001/007).
 * Resolves the `executive` persona through `Modules\Comms\Domain\Support\EnabledWidgetsResolver`
 * — `COM-03`'s own widget-resolution engine, registered against the
 * SAME `Modules\Comms\Domain\Registry\WidgetRegistry` every other
 * persona already uses (see `IntelligenceServiceProvider::registerExecutiveWidgets()`).
 * This action never resolves a widget itself and never builds a
 * second dashboard-aggregation mechanism — it only calls the one that
 * already exists, for a new persona.
 */
final class BuildExecutiveDashboardAction extends Action
{
    protected bool $transactional = false;

    public function __construct(
        private readonly EnabledWidgetsResolver $enabledWidgets,
    ) {}

    /**
     * @return array<int, WidgetResolverResult>
     */
    public function execute(User $user, int $schoolId): array
    {
        $widgets = $this->enabledWidgets->resolve('executive', $schoolId);

        return collect($widgets)
            ->map(fn ($widget) => ($widget->resolver)($user, $schoolId))
            ->filter(fn ($result) => $result !== null && $result->hasData())
            ->values()
            ->all();
    }
}
