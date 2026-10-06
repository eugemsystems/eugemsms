<?php

declare(strict_types=1);

namespace Modules\People\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Modules\Core\Domain\Concerns\BelongsToSchool;
use Modules\People\Database\Factories\ApplicationDocumentFactory;

/**
 * Book C PPL-02 §2. A supporting document uploaded with an application.
 *
 * @property int $id
 * @property int $school_id
 * @property int $application_id
 * @property string $document_type
 * @property int $file_id
 * @property bool $is_verified
 * @property int|null $verified_by
 */
class ApplicationDocument extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<ApplicationDocumentFactory> */
    use HasFactory;

    protected $table = 'application_documents';

    public $timestamps = false;

    protected $fillable = ['school_id', 'application_id', 'document_type', 'file_id', 'is_verified', 'verified_by'];

    protected function casts(): array
    {
        return [
            'is_verified' => 'boolean',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return ApplicationDocumentFactory::new();
    }
}
