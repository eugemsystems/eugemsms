<?php

declare(strict_types=1);

namespace Modules\Academic\Domain\Actions;

use InvalidArgumentException;
use Modules\Academic\Domain\DataObjects\CreateLibraryCopyData;
use Modules\Academic\Models\LibraryCopy;
use Modules\Academic\Models\LibraryItem;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\Actions\Documents\AllocateNumberAction;
use Modules\Core\Domain\DataObjects\Documents\AllocateNumberData;

/**
 * ACT-CreateLibraryCopy (Book K ACA-10 §2/BR-ACA-10-001).
 * `accession_number` is allocated through `CORE-06`
 * (`document_type` = "library_accession") — gapless per school, not a
 * locally-generated sequence.
 */
final class CreateLibraryCopyAction extends Action
{
    public function __construct(
        private readonly AllocateNumberAction $allocateNumber,
    ) {}

    public function execute(CreateLibraryCopyData $data): LibraryCopy
    {
        $item = LibraryItem::findOrFail($data->itemId);

        if (! $item->is_active) {
            throw new InvalidArgumentException('Copies cannot be added to a retired catalogue item.');
        }

        if (! in_array($data->condition, ['new', 'good', 'fair', 'poor'], true)) {
            throw new InvalidArgumentException("Unknown condition [{$data->condition}].");
        }

        if ($data->barcode !== null && LibraryCopy::query()->where('school_id', $item->school_id)->where('barcode', $data->barcode)->exists()) {
            throw new InvalidArgumentException("Barcode {$data->barcode} is already on another copy.");
        }

        return $this->transaction(function () use ($item, $data): LibraryCopy {
            $number = $this->allocateNumber->execute(new AllocateNumberData(
                schoolId: $item->school_id,
                documentType: 'library_accession',
                allocatedByUserId: $data->allocatedByUserId,
            ));

            return LibraryCopy::create([
                'school_id' => $item->school_id,
                'item_id' => $item->id,
                'accession_number' => $number->formatted_number,
                'barcode' => $data->barcode,
                'condition' => $data->condition,
                'acquired_on' => $data->acquiredOn?->toDateString(),
                'status' => 'available',
            ]);
        });
    }
}
