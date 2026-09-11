<?php

declare(strict_types=1);

namespace Modules\Academic\Domain\Actions;

use Modules\Academic\Domain\DataObjects\CreateLibraryItemData;
use Modules\Academic\Models\LibraryItem;
use Modules\Core\Domain\Actions\Action;

final class CreateLibraryItemAction extends Action
{
    public function execute(CreateLibraryItemData $data): LibraryItem
    {
        return $this->transaction(fn (): LibraryItem => LibraryItem::create([
            'school_id' => $data->schoolId,
            'isbn' => $data->isbn,
            'title' => $data->title,
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
