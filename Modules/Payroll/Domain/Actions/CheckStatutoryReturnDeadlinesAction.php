<?php

declare(strict_types=1);

namespace Modules\Payroll\Domain\Actions;

use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\Support\Settings\ScopeChain;
use Modules\Core\Domain\Support\Settings\SettingResolver;
use Modules\Payroll\Domain\Events\StatutoryReturnDue;
use Modules\Payroll\Domain\Events\StatutoryReturnOverdue;
use Modules\Payroll\Models\StatutoryReturn;

/**
 * ACT-CheckStatutoryReturnDeadlines (Book H3 PPL-05 §5/BR-PPL-05-021).
 * A scan a scheduler calls daily, mirroring this codebase's own
 * established "periodic scan, fire one event per still-outstanding
 * item" pattern (`Modules\Security`'s `CheckOverdueKeysAction`,
 * `Modules\Sport`'s `CheckOverdueEquipmentAction`). Not-yet-submitted
 * returns due within `payroll.return_alert_days` fire
 * `StatutoryReturnDue` once per configured day (7, 3, 1 by default);
 * anything past its due date and still not `submitted`/`acknowledged`/
 * `paid` fires `StatutoryReturnOverdue` every time this runs, which is
 * what makes the daily head alert daily.
 */
final class CheckStatutoryReturnDeadlinesAction extends Action
{
    private const array UNRESOLVED_STATUSES = ['pending', 'prepared', 'reviewed'];

    public function __construct(
        private readonly SettingResolver $settings,
    ) {}

    /**
     * @return array{due: Collection<int, StatutoryReturn>, overdue: Collection<int, StatutoryReturn>}
     */
    public function execute(int $schoolId): array
    {
        $today = Carbon::now()->toDateString();
        $alertDays = (array) $this->settings->get('payroll.return_alert_days', new ScopeChain(schoolId: $schoolId));

        $unresolved = StatutoryReturn::where('school_id', $schoolId)
            ->whereIn('status', self::UNRESOLVED_STATUSES)
            ->get();

        $due = new Collection;
        $overdue = new Collection;

        foreach ($unresolved as $return) {
            $daysUntilDue = (int) Carbon::now()->startOfDay()->diffInDays($return->due_date->copy()->startOfDay(), false);

            if ($daysUntilDue < 0) {
                $overdue->push($return);
                event(new StatutoryReturnOverdue($return));

                continue;
            }

            if (in_array($daysUntilDue, $alertDays, true)) {
                $due->push($return);
                event(new StatutoryReturnDue($return, $daysUntilDue));
            }
        }

        return ['due' => $due, 'overdue' => $overdue];
    }
}
