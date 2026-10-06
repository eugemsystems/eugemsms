<?php

declare(strict_types=1);

namespace Modules\People\Livewire\Concerns;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Modules\Core\Domain\Actions\Files\UploadFileAction;
use Modules\Core\Domain\DataObjects\Files\UploadFileData;

/**
 * Puts a Livewire upload into the CORE-10 file vault in one of People's file
 * categories and returns the vault file's id (or null, with an error on the
 * property, when the upload could not be read).
 */
trait UploadsToVault
{
    protected function storeInVault(?TemporaryUploadedFile $file, string $category, string $property = 'file', ?Carbon $expiresOn = null): ?int
    {
        $contents = $file?->get();

        if ($file === null || $contents === false || $contents === null) {
            $this->addError($property, __('The uploaded file could not be read — please try again.'));

            return null;
        }

        return app(UploadFileAction::class)->execute(new UploadFileData(
            schoolId: $this->school->id,
            category: $category,
            contents: $contents,
            originalName: $file->getClientOriginalName(),
            uploadedByUserId: (int) Auth::id(),
            attachableType: $category,
            expiresOn: $expiresOn,
        ))->id;
    }
}
