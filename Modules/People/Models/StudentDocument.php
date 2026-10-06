<?php

declare(strict_types=1);

namespace Modules\People\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Modules\Core\Domain\Concerns\BelongsToSchool;
use Modules\People\Database\Factories\StudentDocumentFactory;

/**
 * Book C PPL-01 §2/BR-PPL-01-021. Identity, permit and transfer documents on a learner's file.
 *
 * @property int $id
 * @property int $school_id
 * @property int $student_id
 * @property string $document_type
 * @property int $file_id
 * @property string|null $reference_number
 * @property Carbon|null $issued_on
 * @property Carbon|null $expires_on
 * @property bool $is_verified
 * @property int|null $verified_by
 * @property Carbon|null $verified_at
 * @property bool $is_original_sighted
 * @property string|null $notes
 * @property int|null $uploaded_by
 */
class StudentDocument extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<StudentDocumentFactory> */
    use HasFactory;

    protected $table = 'student_documents';

    public $timestamps = false;

    public const CREATED_AT = 'created_at';

    public const UPDATED_AT = null;

    protected $fillable = ['school_id', 'student_id', 'document_type', 'file_id', 'reference_number', 'issued_on', 'expires_on', 'is_verified', 'verified_by', 'verified_at', 'is_original_sighted', 'notes', 'uploaded_by', 'created_at'];

    protected function casts(): array
    {
        return [
            'issued_on' => 'date',
            'expires_on' => 'date',
            'verified_at' => 'datetime',
            'is_verified' => 'boolean',
            'is_original_sighted' => 'boolean',
            'created_at' => 'datetime',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return StudentDocumentFactory::new();
    }
}
