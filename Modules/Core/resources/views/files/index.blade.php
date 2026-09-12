<div>
    <div class="d-flex align-items-center gap-2 mb-4 flex-wrap">
        <a href="{{ route('schools.index') }}" class="btn btn-icon btn-outline-secondary btn-sm" wire:navigate>
            <i class="ri ri-arrow-left-line"></i>
        </a>
        <div class="flex-grow-1">
            <h4 class="mb-1">{{ __('File vault') }}</h4>
            <p class="text-body-secondary mb-0">{{ __('Every file uploaded for :school, across every module.', ['school' => $school->name]) }}</p>
        </div>
        <a href="{{ route('files.quota', $school) }}" class="btn btn-outline-secondary" wire:navigate>
            <i class="ri ri-database-2-line me-1"></i>{{ __('Storage quota') }}
        </a>
        <a href="{{ route('files.categories', $school) }}" class="btn btn-outline-secondary" wire:navigate>
            <i class="ri ri-list-settings-line me-1"></i>{{ __('Categories') }}
        </a>
    </div>

    <x-data-table
        :columns="$this->tableColumns()"
        :rows="$files"
        :search="$search"
        :sort-column="$sortColumn"
        :sort-direction="$sortDirection"
        :per-page="$perPage"
        :column-filters="$columnFilters"
        :hidden-columns="$hiddenColumns"
        :per-page-options="$this->perPageOptions()"
    >
        @forelse ($files as $file)
            <tr wire:key="file-{{ $file->id }}">
                @if ($this->columnVisible('original_name'))
                    <td>
                        <div class="fw-medium">{{ $file->original_name }}</div>
                        @if ($file->is_sensitive)
                            <span class="badge text-bg-warning">{{ __('Sensitive') }}</span>
                        @endif
                    </td>
                @endif
                @if ($this->columnVisible('category'))
                    <td>{{ $categoryOptions[$file->category] ?? $file->category }}</td>
                @endif
                @if ($this->columnVisible('scan_status'))
                    <td>
                        @php
                            $scanBadge = match ($file->scan_status) {
                                'clean', 'skipped' => 'success',
                                'infected' => 'danger',
                                default => 'secondary',
                            };
                        @endphp
                        <span class="badge text-bg-{{ $scanBadge }}">{{ \Illuminate\Support\Str::headline($file->scan_status) }}</span>
                    </td>
                @endif
                @if ($this->columnVisible('size_bytes'))
                    <td>{{ number_format($file->size_bytes / 1024, 1) }} KB</td>
                @endif
                @if ($this->columnVisible('uploaded_by'))
                    <td>{{ $file->uploadedBy?->name ?? '—' }}</td>
                @endif
                @if ($this->columnVisible('created_at'))
                    <td>{{ $file->created_at?->format('d M Y H:i') }}</td>
                @endif
                <td class="text-end">
                    @if (! $file->is_sensitive || $this->canViewSensitive())
                        <button type="button" class="btn btn-icon btn-sm btn-outline-primary" wire:click="download({{ $file->id }})" title="{{ __('Download') }}" aria-label="{{ __('Download') }}">
                            <i class="icon-base ri ri-download-line icon-22px"></i>
                        </button>
                    @endif
                    @if ($this->canDelete())
                        <button type="button" class="btn btn-icon btn-sm btn-outline-danger" wire:click="delete({{ $file->id }})" wire:confirm="{{ __('Delete this file? It can be recovered for a limited retention window before permanent removal.') }}" title="{{ __('Delete') }}" aria-label="{{ __('Delete') }}">
                            <i class="icon-base ri ri-delete-bin-line icon-22px"></i>
                        </button>
                    @endif
                </td>
            </tr>
        @empty
            <tr>
                <td colspan="7" class="text-center text-body-secondary py-4">{{ __('No files uploaded yet.') }}</td>
            </tr>
        @endforelse
    </x-data-table>
</div>
