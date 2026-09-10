<?php

declare(strict_types=1);

namespace Modules\Core\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\Models\DocumentTemplate;
use Modules\Core\Models\School;

/**
 * @extends Factory<DocumentTemplate>
 */
class DocumentTemplateFactory extends Factory
{
    protected $model = DocumentTemplate::class;

    public function definition(): array
    {
        return [
            'school_id' => School::factory(),
            'template_type' => 'receipt',
            'name' => 'Standard Receipt',
            'version' => 1,
            'content' => '<p>Receipt for {{ school.name }}</p>',
            'page_size' => 'A4',
            'orientation' => 'portrait',
            'is_default' => true,
            'is_active' => true,
        ];
    }
}
