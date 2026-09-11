<div>
    <div class="d-flex align-items-center justify-content-between mb-4">
        <div>
            <h4 class="mb-1">{{ __('Configuration profiles') }}</h4>
            <p class="text-body-secondary mb-0">{{ __('Shared across every school in this tenant.') }}</p>
        </div>
        <div class="d-flex gap-2">
            <button type="button" class="btn btn-primary" wire:click="$set('showExportModal', true)">
                <i class="ri ri-upload-line me-1"></i>{{ __('Export this school') }}
            </button>
            <a href="{{ route('settings.index', $school) }}" class="btn btn-outline-secondary" wire:navigate>
                <i class="ri ri-arrow-left-line me-1"></i>{{ __('Back to settings') }}
            </a>
        </div>
    </div>

    <div class="card">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead>
                    <tr>
                        <th>{{ __('Name') }}</th>
                        <th>{{ __('Source school') }}</th>
                        <th>{{ __('Created') }}</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($profiles as $profile)
                        <tr wire:key="profile-{{ $profile->id }}">
                            <td>
                                {{ $profile->name }}
                                @if ($profile->description)
                                    <div class="text-body-secondary small">{{ $profile->description }}</div>
                                @endif
                            </td>
                            <td>{{ $profile->sourceSchool?->name ?? '—' }}</td>
                            <td>{{ $profile->created_at?->format('d M Y H:i') }}</td>
                            <td class="text-end">
                                <button type="button" class="btn btn-sm btn-outline-primary" wire:click="openImportModal({{ $profile->id }})">
                                    {{ __('Import into this school') }}
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="text-center text-body-secondary py-4">
                                {{ __('No configuration profiles yet.') }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @if ($showExportModal)
        <div class="modal show d-block" tabindex="-1" style="background: rgba(0, 0, 0, .5);">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <form wire:submit="export">
                        <div class="modal-header">
                            <h5 class="modal-title">{{ __('Export :school as a profile', ['school' => $school->name]) }}</h5>
                            <button type="button" class="btn-close" wire:click="$set('showExportModal', false)" aria-label="{{ __('Close') }}"></button>
                        </div>
                        <div class="modal-body">
                            <div class="form-floating form-floating-outline mb-3">
                                <input type="text" class="form-control @error('name') is-invalid @enderror" id="export-name" wire:model="exportName" placeholder=" ">
                                <label for="export-name">{{ __('Profile name') }}</label>
                                @error('name')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="form-floating form-floating-outline">
                                <textarea class="form-control" id="export-description" wire:model="exportDescription" style="height: 5rem;" placeholder=" "></textarea>
                                <label for="export-description">{{ __('Description (optional)') }}</label>
                            </div>
                            <p class="text-body-secondary small mt-3 mb-0">
                                {{ __('Carries settings and custom field definitions only — never learner, staff, or financial data, and never encrypted settings.') }}
                            </p>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-outline-secondary" wire:click="$set('showExportModal', false)">{{ __('Cancel') }}</button>
                            <button type="submit" class="btn btn-primary" wire:loading.attr="disabled">{{ __('Export') }}</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif

    @if ($importingProfileId !== null)
        <div class="modal show d-block" tabindex="-1" style="background: rgba(0, 0, 0, .5);">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <form wire:submit="import">
                        <div class="modal-header">
                            <h5 class="modal-title">{{ __('Import into :school', ['school' => $school->name]) }}</h5>
                            <button type="button" class="btn-close" wire:click="$set('importingProfileId', null)" aria-label="{{ __('Close') }}"></button>
                        </div>
                        <div class="modal-body">
                            <div class="form-check mb-2">
                                <input class="form-check-input" type="checkbox" id="overwrite-settings" wire:model="overwriteSettings">
                                <label class="form-check-label" for="overwrite-settings">{{ __('Overwrite existing settings at this school') }}</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="overwrite-fields" wire:model="overwriteCustomFields">
                                <label class="form-check-label" for="overwrite-fields">{{ __('Overwrite existing custom fields at this school') }}</label>
                            </div>
                            <p class="text-body-secondary small mt-3 mb-0">
                                {{ __('Anything already set at this school is left untouched unless you check the matching box above.') }}
                            </p>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-outline-secondary" wire:click="$set('importingProfileId', null)">{{ __('Cancel') }}</button>
                            <button type="submit" class="btn btn-primary" wire:loading.attr="disabled">{{ __('Import') }}</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif
</div>
