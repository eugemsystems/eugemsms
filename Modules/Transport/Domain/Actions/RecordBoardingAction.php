<?php

declare(strict_types=1);

namespace Modules\Transport\Domain\Actions;

use Illuminate\Support\Carbon;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\Actions\Notifications\DispatchNotificationAction;
use Modules\Core\Domain\DataObjects\Notifications\DispatchNotificationData;
use Modules\Core\Domain\Support\Settings\ScopeChain;
use Modules\Core\Domain\Support\Settings\SettingResolver;
use Modules\People\Models\Student;
use Modules\People\Models\StudentGuardian;
use Modules\Transport\Domain\Events\LearnerBoarded;
use Modules\Transport\Models\TripPassenger;
use Throwable;

/**
 * ACT-RecordBoarding (Book H2 OPS-01 §4 ⭐/BR-OPS-01-010/AC-OPS-01-006
 * mobile side). Notifies the guardian on board/alight when the
 * relevant setting is enabled — non-blocking, matching this
 * codebase's own "notification never blocks the operation it attaches
 * to" doctrine.
 */
final class RecordBoardingAction extends Action
{
    public function __construct(
        private readonly SettingResolver $settings,
        private readonly DispatchNotificationAction $dispatchNotification,
    ) {}

    public function execute(int $tripPassengerId, string $event, ?string $boardingMethod = null): TripPassenger
    {
        $passenger = TripPassenger::findOrFail($tripPassengerId);

        return $this->transaction(function () use ($passenger, $event, $boardingMethod): TripPassenger {
            if ($event === 'boarded') {
                $passenger->update(['boarded_at' => Carbon::now(), 'boarding_method' => $boardingMethod, 'status' => 'boarded']);
                $settingKey = 'transport.notify_guardian_on_board';
                $notificationKey = 'transport.learner_boarded';
            } else {
                $passenger->update(['alighted_at' => Carbon::now(), 'status' => 'alighted']);
                $settingKey = 'transport.notify_guardian_on_alight';
                $notificationKey = 'transport.learner_alighted';
            }

            $scope = new ScopeChain(schoolId: $passenger->school_id);

            if ((bool) $this->settings->get($settingKey, $scope)) {
                $this->notifyGuardian($passenger, $notificationKey);
            }

            if ($event === 'boarded') {
                event(new LearnerBoarded($passenger));
            }

            return $passenger;
        });
    }

    private function notifyGuardian(TripPassenger $passenger, string $notificationKey): void
    {
        if ($passenger->student_id === null) {
            return;
        }

        $student = Student::find($passenger->student_id);

        if ($student === null) {
            return;
        }

        $link = StudentGuardian::query()
            ->where('student_id', $student->id)
            ->where('is_primary_contact', true)
            ->where('status', 'active')
            ->with('guardian')
            ->first();

        if ($link === null || $link->guardian === null) {
            return;
        }

        $guardian = $link->guardian;

        try {
            $this->dispatchNotification->execute(new DispatchNotificationData(
                schoolId: $passenger->school_id,
                notificationKey: $notificationKey,
                recipientType: 'guardian',
                addresses: ['sms' => (string) $guardian->primary_phone, 'email' => (string) $guardian->email],
                context: ['student' => ['first_name' => $student->first_name, 'last_name' => $student->last_name]],
                recipientId: $guardian->id,
                relatedType: 'trip_passenger',
                relatedId: $passenger->id,
            ));

            $passenger->update(['guardian_notified_at' => Carbon::now()]);
        } catch (Throwable) {
            // Non-blocking — the boarding record is already durable.
        }
    }
}
