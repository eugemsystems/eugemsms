<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Actions\Settings;

use Illuminate\Support\Facades\Validator;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\DataObjects\Settings\ToggleFeatureFlagData;
use Modules\Core\Domain\Events\Settings\FeatureFlagToggled;
use Modules\Core\Models\FeatureFlag;
use Modules\Core\Models\FeatureFlagOverride;

/**
 * `Core\FeatureFlags\Index` action (Book A CORE-04 §4/§5). With no scope
 * given, flips the global default; with a scope, sets (or replaces) an
 * override at that level.
 */
final class ToggleFeatureFlagAction extends Action
{
    public function execute(ToggleFeatureFlagData $data): void
    {
        $flag = FeatureFlag::where('key', $data->key)->firstOrFail();

        if ($data->scopeType === null) {
            $this->transaction(function () use ($flag, $data): void {
                $flag->is_globally_enabled = $data->isEnabled;
                $flag->save();

                event(new FeatureFlagToggled($flag, null, null, $data->isEnabled));
            });

            return;
        }

        Validator::make(
            ['scope_type' => $data->scopeType],
            ['scope_type' => ['required', 'in:user,school,tenant']],
        )->validate();

        $this->transaction(function () use ($flag, $data): void {
            FeatureFlagOverride::updateOrCreate(
                ['feature_flag_id' => $flag->id, 'scope_type' => $data->scopeType, 'scope_id' => $data->scopeId],
                ['is_enabled' => $data->isEnabled],
            );

            event(new FeatureFlagToggled($flag, $data->scopeType, $data->scopeId, $data->isEnabled));
        });
    }
}
