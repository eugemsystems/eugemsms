<?php

declare(strict_types=1);

namespace Modules\Comms\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Comms\Models\Newsletter;
use Modules\Core\Models\School;

/**
 * @extends Factory<Newsletter>
 */
class NewsletterFactory extends Factory
{
    protected $model = Newsletter::class;

    public function definition(): array
    {
        return [
            'school_id' => School::factory(),
            'issue_number' => 'NL/'.$this->faker->unique()->numerify('####'),
            'title' => $this->faker->sentence(4),
            'content_html' => '<p>'.$this->faker->paragraph().'</p>',
            'audience_scope' => 'whole_school',
            'scheduled_for' => null,
            'sent_at' => null,
            'archive_file_id' => null,
            'status' => 'draft',
        ];
    }
}
