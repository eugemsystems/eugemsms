<?php

declare(strict_types=1);

namespace Modules\Compliance\Domain\Actions;

use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Modules\Compliance\Domain\Events\StatutorySchoolReturnDeadlineDue;
use Modules\Compliance\Domain\Events\StatutorySchoolReturnDeadlineOverdue;
use Modules\Compliance\Models\StatutorySchoolReturn;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\Support\Settings\ScopeChain;
use Modules\Core\Domain\Support\Settings\SettingResolver;

/**
 * ACT-CheckStatutorySchoolReturnDeadlines (Book H3 CMP-02 §3/BR-CMP-02
 * -006). Same "periodic scan, one event per still-outstanding item"
 * shape as every other deadline scan in this book
 * (`Modules\Payroll\Domain\Actions\CheckStatutoryReturnDeadlinesAction`,
 * `Modules\Compliance\Domain\Actions\CheckZimsecDeadlinesAction`).
 * 30/14/7 days by default (no "1 day" tier — CMP-02's own rule stops
 * at 7); overdue fires every run, which is what makes the head's
 * alert daily.
 */
final class CheckStatutorySchoolReturnDeadlinesAction extends Action
{
    private const array UNSUBMITTED_STATUSES = ['pending', 'generating', 'validated'];

    public function __construct(
        private readonly SettingResolver $settings,
    ) {}

    /**
     * @return array{due: Collection<int, StatutorySchoolReturn>, overdue: Collection<int, StatutorySchoolReturn>}
     */
    public function execute(int $schoolId): array
    {
        $alertDays = (array) $this->settings->get('compliance.statutory_return_alert_days', new ScopeChain(schoolId: $schoolId));

        $unsubmitted = StatutorySchoolReturn::where('school_id', $schoolId)
            ->whereIn('status', self::UNSUBMITTED_STATUSES)
            ->get();

        $due = new Collection;
        $overdue = new Collection;

        foreach ($unsubmitted as $return) {
            $daysUntilDue = (int) Carbon::now()->startOfDay()->diffInDays($return->due_date->copy()->startOfDay(), false);

            if ($daysUntilDue < 0) {
                $overdue->push($return);
                event(new StatutorySchoolReturnDeadlineOverdue($return));

                continue;
            }

            if (in_array($daysUntilDue, $alertDays, true)) {
                $due->push($return);
                event(new StatutorySchoolReturnDeadlineDue($return, $daysUntilDue));
            }
        }

        return ['due' => $due, 'overdue' => $overdue];
    }
}
