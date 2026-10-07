<?php

declare(strict_types=1);

namespace Modules\Academic\Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Academic\Models\Assessment;
use Modules\Academic\Models\MarkAmendmentRequest;
use Modules\Core\Models\School;
use Modules\People\Models\Student;

/**
 * @extends Factory<MarkAmendmentRequest>
 */
class MarkAmendmentRequestFactory extends Factory
{
    protected $model = MarkAmendmentRequest::class;

    public function definition(): array
    {
        return [
            'school_id' => School::factory(),
            'assessment_id' => Assessment::factory(),
            'student_id' => Student::factory(),
            'new_raw_mark' => 75,
            'new_is_absent' => false,
            'change_reason' => 'Re-marked after a moderation review found a tallying error.',
            'requested_by' => User::factory(),
            'status' => 'pending',
        ];
    }
}
