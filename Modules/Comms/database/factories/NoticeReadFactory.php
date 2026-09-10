<?php

declare(strict_types=1);

namespace Modules\Comms\Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Comms\Models\Notice;
use Modules\Comms\Models\NoticeRead;
use Modules\Core\Models\School;

/**
 * @extends Factory<NoticeRead>
 */
class NoticeReadFactory extends Factory
{
    protected $model = NoticeRead::class;

    public function definition(): array
    {
        return [
            'school_id' => School::factory(),
            'notice_id' => Notice::factory(),
            'user_id' => User::factory(),
            'read_at' => now(),
        ];
    }
}
