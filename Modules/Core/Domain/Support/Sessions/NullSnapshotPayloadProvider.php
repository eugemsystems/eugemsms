<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Support\Sessions;

use Modules\Core\Domain\Contracts\Sessions\SnapshotPayloadProvider;
use Modules\Core\Models\Term;

final class NullSnapshotPayloadProvider implements SnapshotPayloadProvider
{
    public function payload(Term $term): array
    {
        return [
            'note' => 'No financial or academic payload provider is registered yet (ships with Book B/Book D).',
        ];
    }

    public function rowCounts(Term $term): array
    {
        return [];
    }
}
