<?php

declare(strict_types=1);

namespace Modules\Academic\Domain\Actions;

use InvalidArgumentException;
use Modules\Academic\Domain\DataObjects\CreateLibraryItemData;
use Modules\Academic\Models\LibraryItem;
use Modules\Academic\Models\Subject;
use Modules\Core\Domain\Actions\Action;

final class CreateLibraryItemAction extends Action
{
    public function execute(CreateLibraryItemData $data): LibraryItem
    {
        $title = trim($data->title);

        if ($title === '') {
            throw new InvalidArgumentException('An item needs a title.');
        }

        if (! in_array($data->itemCategory, ['textbook', 'reference', 'fiction', 'non_fiction', 'periodical', 'digital'], true)) {
            throw new InvalidArgumentException("Unknown item category [{$data->itemCategory}].");
        }

        if ($data->replacementCostMinor !== null && ($data->replacementCostMinor <= 0 || ($data->currency === null || strlen($data->currency) !== 3))) {
            throw new InvalidArgumentException('A replacement cost must be positive and carry a three-letter currency.');
        }

        if ($data->digitalResourceUrl !== null && ! preg_match('#^https?://#i', $data->digitalResourceUrl)) {
            throw new InvalidArgumentException('A digital resource link must be an http(s) address.');
        }

        if ($data->isbn !== null && LibraryItem::query()->where('school_id', $data->schoolId)->where('isbn', $data->isbn)->exists()) {
            throw new InvalidArgumentException("ISBN {$data->isbn} is already in the catalogue.");
        }

        if ($data->subjectId !== null && ! Subject::query()->whereKey($data->subjectId)->exists()) {
            throw new InvalidArgumentException('That subject does not belong to this school.');
        }

        return $this->transaction(fn (): LibraryItem => LibraryItem::create([
            'school_id' => $data->schoolId,
            'isbn' => $data->isbn,
            'title' => $title,
            'author' => $data->author,
            'publisher' => $data->publisher,
            'edition' => $data->edition,
            'classification' => $data->classification,
            'item_category' => $data->itemCategory,
            'subject_id' => $data->subjectId,
            'grade_level_id' => $data->gradeLevelId,
            'replacement_cost_minor' => $data->replacementCostMinor,
            'currency' => $data->currency,
            'cover_image_file_id' => $data->coverImageFileId,
            'digital_resource_url' => $data->digitalResourceUrl,
            'is_active' => true,
        ]));
    }
}
