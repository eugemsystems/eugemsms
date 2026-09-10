<?php

declare(strict_types=1);

namespace Modules\Academic\Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Academic\Models\CommentBank;
use Modules\Core\Models\School;

/**
 * @extends Factory<CommentBank>
 */
class CommentBankFactory extends Factory
{
    protected $model = CommentBank::class;

    public function definition(): array
    {
        return [
            'school_id' => School::factory(),
            'scope' => 'general',
            'text' => 'A pleasure to teach; consistently engaged in class.',
            'created_by' => User::factory(),
            'is_active' => true,
        ];
    }
}
