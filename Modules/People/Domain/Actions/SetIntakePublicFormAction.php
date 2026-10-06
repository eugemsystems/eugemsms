<?php

declare(strict_types=1);

namespace Modules\People\Domain\Actions;

use Illuminate\Support\Str;
use Modules\Core\Domain\Actions\Action;
use Modules\People\Models\Intake;

/**
 * Publishes or withdraws an intake's public enquiry form (BR-PPL-02-001). The slug is generated once
 * (random suffix, so it cannot be guessed from the intake name) and kept when the form is switched
 * off, so a link already shared with parents resumes working if the form is switched back on.
 */
final class SetIntakePublicFormAction extends Action
{
    public function execute(int $intakeId, bool $enabled): Intake
    {
        return $this->transaction(function () use ($intakeId, $enabled): Intake {
            $intake = Intake::query()->lockForUpdate()->findOrFail($intakeId);

            $intake->forceFill([
                'public_form_enabled' => $enabled,
                'public_form_slug' => $intake->public_form_slug ?? Str::slug($intake->name).'-'.Str::lower(Str::random(8)),
            ])->save();

            return $intake;
        });
    }
}
