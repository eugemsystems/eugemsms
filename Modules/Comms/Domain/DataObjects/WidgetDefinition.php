<?php

declare(strict_types=1);

namespace Modules\Comms\Domain\DataObjects;

use App\Models\User;
use Closure;

/**
 * Book I COM-03 §2/3 ⭐/BR-COM-03-001. `resolver` calls its owning
 * module's domain layer directly — it never queries that module's
 * tables itself (the same cross-module boundary discipline as every
 * other interface in this specification).
 */
final readonly class WidgetDefinition
{
    /**
     * @param  Closure(User, int): ?WidgetResolverResult  $resolver  (the authenticated portal user, schoolId) => result, or null when there's genuinely nothing to show
     */
    public function __construct(
        public string $key,
        public string $moduleCode,
        public string $persona,
        public string $title,
        public string $dataEndpoint,
        public Closure $resolver,
        public ?int $minGradeOrdinal = null,
        public ?string $requiresModule = null,
        public bool $defaultEnabled = true,
        public int $defaultSortOrder = 0,
    ) {}
}
