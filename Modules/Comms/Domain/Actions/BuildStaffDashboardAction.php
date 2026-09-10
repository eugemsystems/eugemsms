<?php

declare(strict_types=1);

namespace Modules\Comms\Domain\Actions;

use App\Models\User;
use Modules\Comms\Domain\DataObjects\DashboardResult;
use Modules\Comms\Domain\Exceptions\PersonaNotFoundException;
use Modules\Comms\Domain\Support\EnabledWidgetsResolver;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Models\Notification;
use Modules\People\Models\Staff;

/**
 * ACT-BuildStaffDashboard (Book I COM-05 §5).
 */
final class BuildStaffDashboardAction extends Action
{
    protected bool $transactional = false;

    public function __construct(
        private readonly EnabledWidgetsResolver $enabledWidgets,
    ) {}

    public function execute(User $user, int $schoolId): DashboardResult
    {
        $staff = Staff::where('school_id', $schoolId)->where('user_id', $user->id)->first();

        if ($staff === null) {
            throw PersonaNotFoundException::forUser($user->id, 'staff');
        }

        $widgets = $this->enabledWidgets->resolve('staff', $schoolId);

        $panels = collect($widgets)
            ->map(fn ($widget) => ($widget->resolver)($user, $schoolId))
            ->filter(fn ($result) => $result !== null && $result->hasData())
            ->values()
            ->all();

        $unread = Notification::where('recipient_type', 'staff')
            ->where('recipient_id', $staff->id)
            ->where('channel', 'in_app')
            ->whereNull('read_at')
            ->count();

        return new DashboardResult(widgets: $panels, unreadNotifications: $unread);
    }
}
