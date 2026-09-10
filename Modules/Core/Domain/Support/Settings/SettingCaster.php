<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Support\Settings;

use Illuminate\Support\Carbon;
use Modules\Core\Models\SettingDefinition;

/**
 * Book A CORE-04 §3 — turns a setting's raw stored string (always a
 * string in `setting_values.value`/`setting_definitions.default_value`,
 * per the migration) into the PHP type `data_type` promises.
 */
final class SettingCaster
{
    public static function cast(SettingDefinition $definition, ?string $raw): mixed
    {
        if ($raw === null) {
            return null;
        }

        return match ($definition->data_type) {
            'int' => (int) $raw,
            'float', 'money' => (float) $raw,
            'bool' => filter_var($raw, FILTER_VALIDATE_BOOLEAN),
            'json', 'array' => json_decode($raw, true),
            'date' => Carbon::parse($raw)->startOfDay(),
            'time' => Carbon::parse($raw),
            default => $raw,
        };
    }
}
