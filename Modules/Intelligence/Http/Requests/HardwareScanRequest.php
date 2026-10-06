<?php

declare(strict_types=1);

namespace Modules\Intelligence\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * `POST /api/v1/hardware/scan` body (Book J INT-04 §3). `target_id` is the id the owning
 * module's route needs (the roll call for `roll_call`, the checkpoint for `gate`); the
 * spec's example body omits it, so it is an assumption recorded in PROGRESS.md.
 * The OpenAPI document reads these rules.
 */
final class HardwareScanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'device_id' => ['required', 'string', 'size:26'],
            'tag' => ['required', 'string', 'max:100'],
            'scanned_at' => ['required', 'date'],
            'target_id' => ['required', 'integer', 'min:1'],
            'direction' => ['sometimes', 'string', 'in:in,out'],
        ];
    }
}
