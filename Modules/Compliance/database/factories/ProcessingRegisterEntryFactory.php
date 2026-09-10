<?php

declare(strict_types=1);

namespace Modules\Compliance\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Compliance\Models\ProcessingRegisterEntry;
use Modules\Core\Models\School;

/**
 * @extends Factory<ProcessingRegisterEntry>
 */
class ProcessingRegisterEntryFactory extends Factory
{
    protected $model = ProcessingRegisterEntry::class;

    public function definition(): array
    {
        return [
            'school_id' => School::factory(),
            'activity_name' => 'Learner enrolment records',
            'purpose' => 'Administering admission and ongoing enrolment.',
            'lawful_basis' => 'contract',
            'data_categories' => ['bio_data', 'contact_details'],
            'subject_categories' => ['student'],
            'recipients' => null,
            'retention_schedule_id' => null,
            'involves_minors' => true,
            'is_special_category' => false,
            'security_measures' => 'Row-level tenant isolation, field-level encryption for national IDs.',
            'owning_module' => 'PPL-01',
            'last_reviewed_on' => now()->toDateString(),
        ];
    }
}
