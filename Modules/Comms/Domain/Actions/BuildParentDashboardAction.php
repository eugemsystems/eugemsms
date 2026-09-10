<?php

declare(strict_types=1);

namespace Modules\Comms\Domain\Actions;

use App\Models\User;
use Modules\Comms\Domain\DataObjects\DashboardResult;
use Modules\Comms\Domain\Exceptions\PersonaNotFoundException;
use Modules\Comms\Domain\Support\EnabledWidgetsResolver;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Models\Notification;
use Modules\People\Models\Guardian;
use Modules\People\Models\StudentGuardian;

/**
 * ACT-BuildParentDashboard (Book I COM-03 §3 ⭐/BR-COM-03-001/002
 * (AC-COM-03-001)). One call, not eleven: each enabled widget's own
 * resolver calls its OWNING module's domain layer — this action never
 * computes a fee balance, a wallet balance, or anything else itself,
 * it parallel-assembles (sequentially in PHP — genuine async fan-out
 * needs a queue/promise layer this pass doesn't add).
 */
final class BuildParentDashboardAction extends Action
{
    protected bool $transactional = false;

    public function __construct(
        private readonly EnabledWidgetsResolver $enabledWidgets,
    ) {}

    public function execute(User $user, int $schoolId): DashboardResult
    {
        $guardian = Guardian::where('school_id', $schoolId)->where('user_id', $user->id)->first();

        if ($guardian === null) {
            throw PersonaNotFoundException::forUser($user->id, 'guardian');
        }

        $children = StudentGuardian::where('school_id', $schoolId)
            ->where('guardian_id', $guardian->id)
            ->where('status', 'active')
            ->with('student')
            ->get()
            ->pluck('student')
            ->filter()
            ->values();

        $widgets = $this->enabledWidgets->resolve('parent', $schoolId);

        $panels = collect($widgets)
            ->map(fn ($widget) => ($widget->resolver)($user, $schoolId))
            ->filter(fn ($result) => $result !== null && $result->hasData())
            ->values()
            ->all();

        $unread = Notification::where('recipient_type', 'guardian')
            ->where('recipient_id', $guardian->id)
            ->where('channel', 'in_app')
            ->whereNull('read_at')
            ->count();

        return new DashboardResult(
            widgets: $panels,
            unreadNotifications: $unread,
            children: $children->map(fn ($student): array => [
                'id' => $student->id, 'first_name' => $student->first_name, 'last_name' => $student->last_name,
            ])->all(),
        );
    }
}
