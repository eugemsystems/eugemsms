<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Actions\Settings;

use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\DataObjects\Settings\ResetSettingData;
use Modules\Core\Domain\Support\Settings\SettingResolver;
use Modules\Core\Models\SettingChangeLog;
use Modules\Core\Models\SettingValue;

/**
 * The Setting editor screen's "Reset to inherited" action (Book A
 * CORE-04 §5). Deletes the value at this exact scope so resolution falls
 * through to the next scope up (or the definition default) —
 * BR-CORE-04-006 still applies: the reset itself is logged.
 */
final class ResetSettingToInheritedAction extends Action
{
    public function execute(ResetSettingData $data): void
    {
        $this->transaction(function () use ($data): void {
            $existing = SettingValue::where('setting_key', $data->key)
                ->where('scope_type', $data->scopeType)
                ->where('scope_id', $data->scopeId)
                ->first();

            if ($existing === null) {
                return;
            }

            $oldValue = $existing->value;
            $existing->delete();

            SettingChangeLog::create([
                'setting_key' => $data->key,
                'scope_type' => $data->scopeType,
                'scope_id' => $data->scopeId,
                'old_value' => $oldValue,
                'new_value' => null,
                'changed_by' => $data->performedByUserId,
                'ip_address' => $data->ipAddress,
                'changed_at' => now(),
            ]);

            SettingResolver::forget($data->key, $data->scopeType, $data->scopeId);
        });
    }
}
