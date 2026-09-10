<?php

declare(strict_types=1);

namespace Modules\Core\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\Core\Database\Factories\DocumentFactory;
use Modules\Core\Domain\Concerns\BelongsToSchool;
use Modules\Core\Domain\Concerns\HasUlid;

/**
 * Book A CORE-06 §2. Every generated artefact.
 *
 * @property int $id
 * @property string $ulid
 * @property int $school_id
 * @property int|null $academic_year_id
 * @property int|null $term_id
 * @property string $document_type
 * @property int|null $template_id
 * @property int|null $template_version
 * @property string|null $number
 * @property string|null $documentable_type
 * @property int|null $documentable_id
 * @property string $file_path
 * @property string $file_hash
 * @property int|null $file_size
 * @property string|null $verification_code
 * @property int $generated_by
 * @property Carbon $generated_at
 * @property Carbon|null $expires_at
 * @property int $download_count
 */
class Document extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<DocumentFactory> */
    use HasFactory;

    use HasUlid;

    public $timestamps = false;

    protected $fillable = [
        'school_id', 'academic_year_id', 'term_id', 'document_type', 'template_id',
        'template_version', 'number', 'documentable_type', 'documentable_id',
        'file_path', 'file_hash', 'file_size', 'verification_code', 'generated_by',
        'generated_at', 'expires_at', 'download_count',
    ];

    protected function casts(): array
    {
        return [
            'generated_at' => 'datetime',
            'expires_at' => 'datetime',
            'download_count' => 'integer',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return DocumentFactory::new();
    }

    /**
     * @return BelongsTo<DocumentTemplate, $this>
     */
    public function template(): BelongsTo
    {
        return $this->belongsTo(DocumentTemplate::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function generatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'generated_by');
    }
}
