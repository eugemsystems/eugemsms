<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Support\Settings;

use Illuminate\Support\Facades\Auth;
use Modules\Core\Domain\Support\SchoolContext;
use Modules\Core\Domain\Support\SessionContext;

/**
 * Book A CORE-04 §3. A resolved point in the resolution chain for one
 * request — which concrete row id (if any) exists at each scope level.
 * `fromCurrentContext()` reads the same ambient context
 * `SchoolContext`/`SessionContext`/`Auth` already expose elsewhere in
 * Book A Part 1; `SettingResolver` is a context-reading domain service
 * in the same sense those two already are, not an `Action` bound by the
 * "never reads auth()/session() directly" rule (Book A Part 1.1) — that
 * rule governs Actions specifically.
 */
final readonly class ScopeChain
{
    public function __construct(
        public ?int $userId = null,
        public ?int $termId = null,
        public ?int $academicYearId = null,
        public ?int $sectionId = null,
        public ?int $schoolId = null,
        public ?int $tenantId = null,
    ) {}

    public static function fromCurrentContext(): self
    {
        $school = SchoolContext::current();
        $userId = Auth::id();

        return new self(
            userId: $userId !== null ? (int) $userId : null,
            termId: SessionContext::termId(),
            academicYearId: SessionContext::isSet() ? SessionContext::yearId() : null,
            schoolId: $school?->id,
            tenantId: $school?->tenant_id,
        );
    }

    /**
     * @return array<int, array{scope: SettingScope, id: int}>
     */
    public function descendingSpecificity(): array
    {
        $idFor = fn (SettingScope $scope): ?int => match ($scope) {
            SettingScope::User => $this->userId,
            SettingScope::Term => $this->termId,
            SettingScope::AcademicYear => $this->academicYearId,
            SettingScope::Section => $this->sectionId,
            SettingScope::School => $this->schoolId,
            SettingScope::Tenant => $this->tenantId,
            SettingScope::System => null,
        };

        $pairs = [];

        foreach (SettingScope::descendingSpecificity() as $scope) {
            $id = $idFor($scope);

            if ($id !== null) {
                $pairs[] = ['scope' => $scope, 'id' => $id];
            }
        }

        return $pairs;
    }
}
