<?php

declare(strict_types=1);

namespace Modules\Utilities\Domain\Actions;

use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\Actions\Notifications\DispatchNotificationAction;
use Modules\Core\Domain\DataObjects\Notifications\DispatchNotificationData;
use Modules\Core\Domain\Support\Settings\ScopeChain;
use Modules\Core\Domain\Support\Settings\SettingResolver;
use Modules\People\Models\Staff;
use Modules\Utilities\Domain\DataObjects\RecordWaterQualityTestData;
use Modules\Utilities\Domain\Events\WaterQualityFailed;
use Modules\Utilities\Models\WaterSource;
use Throwable;

/**
 * ACT-RecordWaterQualityTest (Book H2 OPS-04 §4 ⭐⭐/BR-OPS-04-017/
 * AC-OPS-04-009). A `not_potable` result alerts the nurse — reusing
 * `Modules\Welfare`'s own real `health.nurse_staff_id` setting rather
 * than inventing a second nurse reference — and the catering manager,
 * immediately, non-blocking.
 */
final class RecordWaterQualityTestAction extends Action
{
    public function __construct(
        private readonly SettingResolver $settings,
        private readonly DispatchNotificationAction $dispatchNotification,
    ) {}

    public function execute(RecordWaterQualityTestData $data): WaterSource
    {
        $source = WaterSource::findOrFail($data->waterSourceId);

        return $this->transaction(function () use ($data, $source): WaterSource {
            $source->update([
                'water_quality_status' => $data->qualityStatus,
                'last_quality_test_on' => $data->testedOn->toDateString(),
            ]);

            if ($data->qualityStatus === 'not_potable') {
                event(new WaterQualityFailed($source));
                $this->notifyStaffSetting($source, 'health.nurse_staff_id', 'utilities.water_quality_failed_nurse');
                $this->notifyStaffSetting($source, 'utilities.catering_manager_staff_id', 'utilities.water_quality_failed_catering');
            }

            return $source;
        });
    }

    private function notifyStaffSetting(WaterSource $source, string $settingKey, string $notificationKey): void
    {
        $scope = new ScopeChain(schoolId: $source->school_id);
        $staffId = $this->settings->get($settingKey, $scope);

        if ($staffId === null) {
            return;
        }

        $staff = Staff::find((int) $staffId);

        if ($staff?->user_id === null) {
            return;
        }

        try {
            $this->dispatchNotification->execute(new DispatchNotificationData(
                schoolId: $source->school_id,
                notificationKey: $notificationKey,
                recipientType: 'staff',
                addresses: ['email' => (string) ($staff->work_email ?? $staff->personal_email)],
                context: ['source' => ['name' => $source->name]],
                recipientId: $staff->user_id,
                relatedType: 'water_source',
                relatedId: $source->id,
                urgent: true,
            ));
        } catch (Throwable) {
            // Non-blocking.
        }
    }
}
