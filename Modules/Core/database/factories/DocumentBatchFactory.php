<?php

declare(strict_types=1);

namespace Modules\Core\Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\Models\DocumentBatch;
use Modules\Core\Models\DocumentTemplate;
use Modules\Core\Models\School;

/**
 * @extends Factory<DocumentBatch>
 */
class DocumentBatchFactory extends Factory
{
    protected $model = DocumentBatch::class;

    public function definition(): array
    {
        return [
            'school_id' => School::factory(),
            'document_type' => 'receipt',
            'template_id' => DocumentTemplate::factory(),
            'total_count' => 10,
            'status' => 'queued',
            'requested_by' => User::factory(),
        ];
    }
}
