<?php

declare(strict_types=1);

namespace Modules\Compliance\Domain\Actions;

use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Modules\Compliance\Domain\Events\ZimsecRegistrationDeadlineDue;
use Modules\Compliance\Domain\Events\ZimsecRegistrationDeadlineOverdue;
use Modules\Compliance\Models\ZimsecRegistration;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\Support\Settings\ScopeChain;
use Modules\Core\Domain\Support\Settings\SettingResolver;

/**
 * ACT-CheckZimsecDeadlines (Book H3 CMP-01 §3/BR-CMP-01-008). A scan a
 * scheduler calls daily, mirroring this codebase's own established
 * "periodic scan, fire one event per still-outstanding item" pattern
 * (`Modules\Payroll`'s `CheckStatutoryReturnDeadlinesAction`,
 * `Modules\Security`'s `CheckOverdueKeysAction`). Not-yet-submitted
 * registrations due within `compliance.zimsec_alert_days` fire
 * `ZimsecRegistrationDeadlineDue` once per configured day (30, 14, 7, 1
 * by default); anything past `registration_closes_on` and still not
 * submitted fires `ZimsecRegistrationDeadlineOverdue` every time this
 * runs, which is what makes the head's alert daily.
 */
final class CheckZimsecDeadlinesAction extends Action
{
    private const array UNSUBMITTED_STATUSES = ['preparing', 'validating', 'validated', 'exported'];

    public function __construct(
        private readonly SettingResolver $settings,
    ) {}

    /**
     * @return array{due: Collection<int, ZimsecRegistration>, overdue: Collection<int, ZimsecRegistration>}
     */
    public function execute(int $schoolId): array
    {
        $alertDays = (array) $this->settings->get('compliance.zimsec_alert_days', new ScopeChain(schoolId: $schoolId));

        $unsubmitted = ZimsecRegistration::where('school_id', $schoolId)
            ->whereIn('status', self::UNSUBMITTED_STATUSES)
            ->get();

        $due = new Collection;
        $overdue = new Collection;

        foreach ($unsubmitted as $registration) {
            $daysUntilDue = (int) Carbon::now()->startOfDay()->diffInDays($registration->registration_closes_on->copy()->startOfDay(), false);

            if ($daysUntilDue < 0) {
                $overdue->push($registration);
                event(new ZimsecRegistrationDeadlineOverdue($registration));

                continue;
            }

            if (in_array($daysUntilDue, $alertDays, true)) {
                $due->push($registration);
                event(new ZimsecRegistrationDeadlineDue($registration, $daysUntilDue));
            }
        }

        return ['due' => $due, 'overdue' => $overdue];
    }
}
