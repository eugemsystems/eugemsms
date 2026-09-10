<?php

declare(strict_types=1);

namespace Modules\Core\Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\Models\Document;
use Modules\Core\Models\School;

/**
 * @extends Factory<Document>
 */
class DocumentFactory extends Factory
{
    protected $model = Document::class;

    public function definition(): array
    {
        return [
            'school_id' => School::factory(),
            'document_type' => 'receipt',
            'file_path' => 'documents/'.fake()->uuid().'.pdf',
            'file_hash' => hash('sha256', fake()->uuid()),
            'file_size' => fake()->numberBetween(1000, 50000),
            'generated_by' => User::factory(),
            'generated_at' => now(),
            'download_count' => 0,
        ];
    }
}
