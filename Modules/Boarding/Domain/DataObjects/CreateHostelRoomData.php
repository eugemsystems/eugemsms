<?php

declare(strict_types=1);

namespace Modules\Boarding\Domain\DataObjects;

final readonly class CreateHostelRoomData
{
    public function __construct(
        public int $schoolId,
        public int $hostelId,
        public string $roomNumber,
        public string $roomType,
        public int $bedCount,
        public ?int $wingId = null,
        public bool $isGroundFloor = false,
        public ?string $proximityToExit = null,
        public ?string $proximityToAblution = null,
    ) {}
}
