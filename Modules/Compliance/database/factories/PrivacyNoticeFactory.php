<?php

declare(strict_types=1);

namespace Modules\Compliance\Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Compliance\Models\PrivacyNotice;
use Modules\Core\Models\School;

/**
 * @extends Factory<PrivacyNotice>
 */
class PrivacyNoticeFactory extends Factory
{
    protected $model = PrivacyNotice::class;

    public function definition(): array
    {
        return [
            'school_id' => School::factory(),
            'version' => 'v1',
            'title' => 'Privacy Notice',
            'content' => 'How we handle personal data.',
            'effective_from' => now()->toDateString(),
            'requires_reconsent' => false,
            'created_by' => User::factory(),
        ];
    }
}
