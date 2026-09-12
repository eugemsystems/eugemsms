<?php

declare(strict_types=1);

namespace Modules\Core\Domain\DataObjects\Install;

/**
 * `SyncPermissionCatalogueAction` needs no input — it reads the whole
 * catalogue from `PermissionRegistry` — but every Action takes exactly
 * one DTO (BR-GLOBAL-001), so this is intentionally empty rather than a
 * bare no-argument `execute()`.
 */
final readonly class SyncPermissionCatalogueData {}
