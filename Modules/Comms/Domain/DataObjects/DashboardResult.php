<?php

declare(strict_types=1);

namespace Modules\Comms\Domain\DataObjects;

/**
 * Book I COM-03/04/05 §3 ⭐ (AC-COM-03-001). One aggregated payload —
 * the single low-latency call every persona dashboard resolves to.
 */
final readonly class DashboardResult
{
    /**
     * @param  array<int, WidgetResolverResult>  $widgets
     * @param  array<int, array{id: int, first_name: string, last_name: string}>  $children  populated for the parent persona only
     */
    public function __construct(
        public array $widgets,
        public int $unreadNotifications,
        public array $children = [],
    ) {}
}
