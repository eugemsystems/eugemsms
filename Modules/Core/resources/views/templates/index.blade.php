<div>
    <div class="d-flex align-items-center gap-2 mb-4 flex-wrap">
        <a href="{{ route('schools.index') }}" class="btn btn-icon btn-outline-secondary btn-sm" wire:navigate>
            <i class="ri ri-arrow-left-line"></i>
        </a>
        <div class="flex-grow-1">
            <h4 class="mb-1">{{ __('Document templates') }}</h4>
            <p class="text-body-secondary mb-0">{{ __('Templates used to generate :school\'s documents.', ['school' => $school->name]) }}</p>
        </div>
        <a href="{{ route('templates.create', $school) }}" class="btn btn-primary" wire:navigate>
            <i class="ri ri-add-line me-1"></i>{{ __('New template') }}
        </a>
    </div>

    <x-data-table
        :columns="$this->tableColumns()"
        :rows="$templates"
        :search="$search"
        :sort-column="$sortColumn"
        :sort-direction="$sortDirection"
        :per-page="$perPage"
        :column-filters="$columnFilters"
        :hidden-columns="$hiddenColumns"
        :per-page-options="$this->perPageOptions()"
    >
        @forelse ($templates as $template)
            <tr wire:key="template-{{ $template->id }}">
                @if ($this->columnVisible('template_type'))
                    <td>{{ $template->template_type }}</td>
                @endif
                @if ($this->columnVisible('name'))
                    <td>{{ $template->name }}</td>
                @endif
                @if ($this->columnVisible('version'))
                    <td>v{{ $template->version }}</td>
                @endif
                @if ($this->columnVisible('is_default'))
                    <td>
                        @if ($template->is_default)
                            <span class="badge text-bg-success">{{ __('Default') }}</span>
                        @else
                            <span class="badge text-bg-secondary">{{ __('Variant') }}</span>
                        @endif
                    </td>
                @endif
                <td class="text-end">
                    <a href="{{ route('templates.versions', [$school, $template->template_type]) }}" class="btn btn-icon btn-sm btn-outline-secondary" wire:navigate title="{{ __('Version history') }}" aria-label="{{ __('Version history') }}">
                        <i class="icon-base ri ri-history-line icon-22px"></i>
                    </a>
                    <a href="{{ route('templates.edit', [$school, $template]) }}" class="btn btn-icon btn-sm btn-outline-primary" wire:navigate title="{{ __('Edit (new version)') }}" aria-label="{{ __('Edit') }}">
                        <i class="icon-base ri ri-edit-line icon-22px"></i>
                    </a>
                </td>
            </tr>
        @empty
            <tr>
                <td colspan="5" class="text-center text-body-secondary py-4">{{ __('No document templates yet.') }}</td>
            </tr>
        @endforelse
    </x-data-table>
</div>
