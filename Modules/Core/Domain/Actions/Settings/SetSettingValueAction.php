<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Actions\Settings;

use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Validator;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\Contracts\Settings\TenantTierProvider;
use Modules\Core\Domain\DataObjects\Settings\SetSettingValueData;
use Modules\Core\Domain\Events\Settings\SettingChanged;
use Modules\Core\Domain\Exceptions\InvalidSettingScopeException;
use Modules\Core\Domain\Exceptions\InvalidStateTransitionException;
use Modules\Core\Domain\Exceptions\UnregisteredSettingException;
use Modules\Core\Domain\Support\Settings\SettingResolver;
use Modules\Core\Domain\Support\Settings\SettingScope;
use Modules\Core\Models\School;
use Modules\Core\Models\SettingChangeLog;
use Modules\Core\Models\SettingDefinition;
use Modules\Core\Models\SettingValue;
use Modules\Core\Models\Tenant;

/**
 * ACT-SetSettingValue (Book A CORE-04 §3/§4). BR-CORE-04-003: cannot
 * write at a scope more general than the definition's `lowest_scope`.
 * BR-CORE-04-004: validated against `data_type`/`validation_rules` before
 * storing. BR-CORE-04-005: encrypted at rest when the definition says so.
 * BR-CORE-04-006: every change writes an immutable `setting_change_log`
 * row. BR-CORE-04-007: refused once a tier lock applies.
 */
final class SetSettingValueAction extends Action
{
    public function __construct(
        private readonly TenantTierProvider $tierProvider,
    ) {}

    public function execute(SetSettingValueData $data): SettingValue
    {
        $definition = SettingDefinition::where('key', $data->key)->first();

        if ($definition === null) {
            throw new UnregisteredSettingException(
                "Setting [{$data->key}] has not been registered by any module.",
                ['key' => $data->key],
            );
        }

        $lowestScope = SettingScope::from($definition->lowest_scope);

        if ($data->scopeType->isAtLeastAsGeneralAs($lowestScope) && $data->scopeType !== $lowestScope) {
            throw new InvalidSettingScopeException(
                "Setting [{$data->key}] cannot be set above its lowest scope of [{$lowestScope->value}].",
                ['key' => $data->key, 'attempted_scope' => $data->scopeType->value, 'lowest_scope' => $lowestScope->value],
            );
        }

        $this->assertNotTierLocked($definition, $data);

        $stringValue = $this->stringify($definition, $data->value);
        $this->validateValue($definition, $stringValue);

        $storedValue = $definition->is_encrypted && $stringValue !== null
            ? Crypt::encryptString($stringValue)
            : $stringValue;

        return $this->transaction(function () use ($definition, $data, $storedValue): SettingValue {
            $existing = SettingValue::where('setting_key', $data->key)
                ->where('scope_type', $data->scopeType)
                ->where('scope_id', $data->scopeId)
                ->first();

            $oldValue = $existing?->value;

            $settingValue = SettingValue::updateOrCreate(
                ['setting_key' => $data->key, 'scope_type' => $data->scopeType, 'scope_id' => $data->scopeId],
                ['value' => $storedValue, 'set_by' => $data->setByUserId],
            );

            SettingChangeLog::create([
                'setting_key' => $data->key,
                'scope_type' => $data->scopeType,
                'scope_id' => $data->scopeId,
                'old_value' => $definition->is_encrypted ? '[redacted]' : $oldValue,
                'new_value' => $definition->is_encrypted ? '[redacted]' : $storedValue,
                'changed_by' => $data->setByUserId,
                'ip_address' => $data->ipAddress,
                'changed_at' => now(),
            ]);

            SettingResolver::forget($data->key, $data->scopeType, $data->scopeId);

            event(new SettingChanged($data->key, $data->scopeType, $data->scopeId));

            return $settingValue;
        });
    }

    private function assertNotTierLocked(SettingDefinition $definition, SetSettingValueData $data): void
    {
        if ($definition->is_locked_on_tier === null) {
            return;
        }

        $tenant = match ($data->scopeType) {
            SettingScope::Tenant => Tenant::find($data->scopeId),
            SettingScope::School => School::find($data->scopeId)?->tenant,
            default => null,
        };

        if ($tenant === null) {
            return;
        }

        $tier = $this->tierProvider->tierFor($tenant);

        if ($this->tierProvider->isAtLeast($tier, $definition->is_locked_on_tier)) {
            throw new InvalidStateTransitionException(
                "Setting [{$definition->key}] cannot be changed on the [{$tier}] tier.",
                ['key' => $definition->key, 'tier' => $tier],
            );
        }
    }

    private function stringify(SettingDefinition $definition, mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        return match ($definition->data_type) {
            'bool' => $value ? '1' : '0',
            'json', 'array' => json_encode($value, JSON_THROW_ON_ERROR),
            default => (string) $value,
        };
    }

    private function validateValue(SettingDefinition $definition, ?string $value): void
    {
        $rules = ['sometimes'];

        if ($definition->validation_rules !== null) {
            $rules = array_merge($rules, explode('|', $definition->validation_rules));
        } else {
            $rules[] = match ($definition->data_type) {
                'int' => 'integer',
                'float', 'money' => 'numeric',
                'bool' => 'boolean',
                'date' => 'date',
                default => 'string',
            };
        }

        Validator::make(
            array_filter(['value' => $value], fn (mixed $v): bool => $v !== null),
            ['value' => $rules],
        )->validate();
    }
}
