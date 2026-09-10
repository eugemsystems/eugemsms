<?php

declare(strict_types=1);

namespace Modules\Comms\Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Comms\Models\Notice;
use Modules\Core\Models\School;

/**
 * @extends Factory<Notice>
 */
class NoticeFactory extends Factory
{
    protected $model = Notice::class;

    public function definition(): array
    {
        return [
            'school_id' => School::factory(),
            'title' => $this->faker->sentence(4),
            'body' => $this->faker->paragraph(),
            'priority' => 'normal',
            'audience_scope' => 'whole_school',
            'audience_scope_id' => null,
            'is_pinned' => false,
            'publish_at' => now(),
            'expires_at' => null,
            'attachment_file_ids' => null,
            'posted_by' => User::factory(),
            'status' => 'published',
        ];
    }

    public function urgent(): self
    {
        return $this->state(fn (): array => ['priority' => 'urgent']);
    }
}
