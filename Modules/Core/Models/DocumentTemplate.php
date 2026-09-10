<?php

declare(strict_types=1);

namespace Modules\Core\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\Core\Database\Factories\DocumentTemplateFactory;
use Modules\Core\Domain\Concerns\BelongsToSchool;
use Modules\Core\Domain\Concerns\HasUlid;

/**
 * Book A CORE-06 §2/BR-CORE-06-007. Immutable once other documents may
 * reference it as `template_version` — editing creates a new row
 * instead of updating `content` in place.
 *
 * @property int $id
 * @property string $ulid
 * @property int $school_id
 * @property int|null $section_id
 * @property string $template_type
 * @property string $name
 * @property int $version
 * @property string $content
 * @property string|null $styles
 * @property string $page_size
 * @property string $orientation
 * @property array<string, mixed>|null $margins
 * @property string|null $header_content
 * @property string|null $footer_content
 * @property bool $is_default
 * @property bool $is_active
 * @property Carbon|null $effective_from
 * @property Carbon|null $effective_to
 * @property int|null $created_by
 * @property int|null $updated_by
 */
class DocumentTemplate extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<DocumentTemplateFactory> */
    use HasFactory;

    use HasUlid;

    protected $fillable = [
        'school_id', 'section_id', 'template_type', 'name', 'version', 'content',
        'styles', 'page_size', 'orientation', 'margins', 'header_content',
        'footer_content', 'is_default', 'is_active', 'effective_from', 'effective_to',
        'created_by', 'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'margins' => 'array',
            'is_default' => 'boolean',
            'is_active' => 'boolean',
            'effective_from' => 'date',
            'effective_to' => 'date',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return DocumentTemplateFactory::new();
    }

    /**
     * @return BelongsTo<SchoolSection, $this>
     */
    public function section(): BelongsTo
    {
        return $this->belongsTo(SchoolSection::class, 'section_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
