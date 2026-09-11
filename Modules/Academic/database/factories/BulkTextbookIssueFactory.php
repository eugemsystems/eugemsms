<?php

declare(strict_types=1);

namespace Modules\Academic\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Academic\Models\BulkTextbookIssue;
use Modules\Core\Models\School;
use Modules\Core\Models\SchoolClass;
use Modules\Core\Models\Term;

/**
 * @extends Factory<BulkTextbookIssue>
 */
class BulkTextbookIssueFactory extends Factory
{
    protected $model = BulkTextbookIssue::class;

    public function definition(): array
    {
        $school = School::factory();

        return [
            'school_id' => $school,
            'term_id' => Term::factory()->for($school),
            'class_id' => SchoolClass::factory()->for($school),
            'issue_type' => 'term_start_issue',
            'item_ids' => [],
            'total_learners' => 0,
            'completed_count' => 0,
            'exception_count' => 0,
            'status' => 'in_progress',
        ];
    }
}
