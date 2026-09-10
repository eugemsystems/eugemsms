<?php

declare(strict_types=1);

namespace Modules\Core\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\Domain\Support\Settings\SettingScope;
use Modules\Core\Models\SettingValue;

/**
 * @extends Factory<SettingValue>
 */
class SettingValueFactory extends Factory
{
    protected $model = SettingValue::class;

    public function definition(): array
    {
        return [
            'setting_key' => 'test.example',
            'scope_type' => SettingScope::School,
            'scope_id' => 1,
            'value' => 'value',
        ];
    }
}
