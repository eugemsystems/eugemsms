<?php

declare(strict_types=1);

namespace Modules\Comms\Domain\Actions;

use App\Models\User;
use Modules\Comms\Domain\DataObjects\DashboardResult;
use Modules\Comms\Domain\DataObjects\WidgetResolverResult;
use Modules\Comms\Domain\Exceptions\PersonaNotFoundException;
use Modules\Comms\Domain\Support\EnabledWidgetsResolver;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Models\Notification;
use Modules\People\Models\Student;

/**
 * ACT-BuildLearnerDashboard (Book I COM-03 §5/BR-COM-03-001
 * (AC-COM-03-004)). Every widget is filtered through
 * `min_grade_ordinal` (`EnabledWidgetsResolver`) — the age-gating
 * `BR-CORE-05-022` establishes. The `BRD-08` "tell someone"
 * safeguarding entry point is added AFTER that filter, unconditionally
 * — it is not a registry widget at all, so no widget configuration
 * screen can ever remove it, which is the literal guarantee
 * AC-COM-03-004 asks for.
 */
final class BuildLearnerDashboardAction extends Action
{
    protected bool $transactional = false;

    public function __construct(
        private readonly EnabledWidgetsResolver $enabledWidgets,
    ) {}

    public function execute(User $user, int $schoolId): DashboardResult
    {
        $student = Student::where('school_id', $schoolId)->where('user_id', $user->id)->with('gradeLevel')->first();

        if ($student === null) {
            throw PersonaNotFoundException::forUser($user->id, 'student');
        }

        $gradeOrdinal = $student->gradeLevel->ordinal ?? null;
        $widgets = $this->enabledWidgets->resolve('learner', $schoolId, $gradeOrdinal);

        $panels = collect($widgets)
            ->map(fn ($widget) => ($widget->resolver)($user, $schoolId))
            ->filter(fn ($result) => $result !== null && $result->hasData())
            ->values()
            ->all();

        $panels[] = new WidgetResolverResult(
            key: 'safeguarding_tell_someone',
            title: 'Tell Someone',
            summary: ['always_visible' => true],
        );

        $unread = Notification::where('recipient_type', 'student')
            ->where('recipient_id', $student->id)
            ->where('channel', 'in_app')
            ->whereNull('read_at')
            ->count();

        return new DashboardResult(widgets: $panels, unreadNotifications: $unread);
    }
}
