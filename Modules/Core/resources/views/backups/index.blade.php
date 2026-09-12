<div>
    <div class="d-flex align-items-center gap-2 mb-4 flex-wrap">
        <div class="flex-grow-1">
            <h4 class="mb-1">{{ __('Backups') }}</h4>
            <p class="text-body-secondary mb-0">{{ __('Every backup across the installation.') }}</p>
        </div>
        <button type="button" class="btn btn-outline-secondary" wire:click="runRetention" wire:confirm="{{ __('Apply the retention policy now? Expired backups are deleted immediately.') }}">
            <i class="ri ri-recycle-line me-1"></i>{{ __('Apply retention') }}
        </button>
        <button type="button" class="btn btn-primary" wire:click="openCreateModal">
            <i class="ri ri-add-line me-1"></i>{{ __('New backup') }}
        </button>
    </div>

    <x-data-table
        :columns="$this->tableColumns()"
        :rows="$backups"
        :search="$search"
        :sort-column="$sortColumn"
        :sort-direction="$sortDirection"
        :per-page="$perPage"
        :column-filters="$columnFilters"
        :hidden-columns="$hiddenColumns"
        :per-page-options="$this->perPageOptions()"
    >
        @forelse ($backups as $backup)
            <tr wire:key="backup-{{ $backup->id }}">
                @if ($this->columnVisible('type'))
                    <td><a href="{{ route('backups.show', $backup) }}" wire:navigate>{{ \Illuminate\Support\Str::headline($backup->type) }}</a></td>
                @endif
                @if ($this->columnVisible('scope'))
                    <td>{{ \Illuminate\Support\Str::headline($backup->scope) }}{{ $backup->scope_id ? " #{$backup->scope_id}" : '' }}</td>
                @endif
                @if ($this->columnVisible('status'))
                    @php
                        $badge = match ($backup->status) {
                            'completed', 'verified' => 'success',
                            'failed', 'expired' => 'danger',
                            default => 'warning',
                        };
                    @endphp
                    <td><span class="badge text-bg-{{ $badge }}">{{ \Illuminate\Support\Str::headline($backup->status) }}</span></td>
                @endif
                @if ($this->columnVisible('size_bytes'))
                    <td>{{ number_format($backup->size_bytes / (1024 * 1024), 2) }} MB</td>
                @endif
                @if ($this->columnVisible('triggered_by'))
                    <td>{{ \Illuminate\Support\Str::headline($backup->triggered_by) }}</td>
                @endif
                @if ($this->columnVisible('started_at'))
                    <td>{{ $backup->started_at->format('d M Y H:i') }}</td>
                @endif
                <td class="text-end">
                    @if (in_array($backup->status, ['completed', 'verified'], true))
                        <button type="button" class="btn btn-icon btn-sm btn-outline-primary" wire:click="runRestoreTest({{ $backup->id }})" title="{{ __('Run restore test') }}" aria-label="{{ __('Run restore test') }}">
                            <i class="icon-base ri ri-shield-check-line icon-22px"></i>
                        </button>
                    @endif
                </td>
            </tr>
        @empty
            <tr>
                <td colspan="7" class="text-center text-body-secondary py-4">{{ __('No backups have been taken yet.') }}</td>
            </tr>
        @endforelse
    </x-data-table>

    @if ($showCreateModal)
        <div class="modal show d-block" tabindex="-1" style="background: rgba(0, 0, 0, .5);">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <form wire:submit="create">
                        <div class="modal-header">
                            <h5 class="modal-title">{{ __('New backup') }}</h5>
                            <button type="button" class="btn-close" wire:click="$set('showCreateModal', false)" aria-label="{{ __('Close') }}"></button>
                        </div>
                        <div class="modal-body">
                            <div class="form-floating form-floating-outline mb-3">
                                <select class="form-select @error('type') is-invalid @enderror" id="type" wire:model.live="type">
                                    <option value="database">{{ __('Database') }}</option>
                                    <option value="files">{{ __('Files') }}</option>
                                    <option value="full">{{ __('Full (database + files)') }}</option>
                                    <option value="school_export">{{ __('One school (logical export)') }}</option>
                                </select>
                                <label for="type">{{ __('Type') }}</label>
                                @error('type') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>

                            @if ($type === 'school_export')
                                <div class="form-floating form-floating-outline">
                                    <select class="form-select @error('schoolId') is-invalid @enderror" id="schoolId" wire:model="schoolId">
                                        <option value="">{{ __('Select a school') }}</option>
                                        @foreach ($schools as $school)
                                            <option value="{{ $school->id }}">{{ $school->name }}</option>
                                        @endforeach
                                    </select>
                                    <label for="schoolId">{{ __('School') }}</label>
                                    @error('schoolId') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>
                            @endif
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-outline-secondary" wire:click="$set('showCreateModal', false)">{{ __('Cancel') }}</button>
                            <button type="submit" class="btn btn-primary" wire:loading.attr="disabled">{{ __('Create') }}</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif
</div>
