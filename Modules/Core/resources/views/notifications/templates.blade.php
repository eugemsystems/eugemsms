<div>
    <div class="d-flex align-items-center gap-2 mb-4 flex-wrap">
        <a href="{{ route('notifications.log', $school) }}" class="btn btn-icon btn-outline-secondary btn-sm" wire:navigate>
            <i class="ri ri-arrow-left-line"></i>
        </a>
        <div class="flex-grow-1">
            <h4 class="mb-1">{{ __('Notification templates') }}</h4>
            <p class="text-body-secondary mb-0">{{ __('School templates plus system defaults.') }}</p>
        </div>
        <a href="{{ route('notifications.templates.create', $school) }}" class="btn btn-primary" wire:navigate>
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
        :with-actions="false"
    >
        @forelse ($templates as $template)
            <tr wire:key="template-{{ $template->id }}">
                @if ($this->columnVisible('key'))
                    <td>{{ $template->key }}</td>
                @endif
                @if ($this->columnVisible('channel'))
                    <td>{{ \Illuminate\Support\Str::headline($template->channel) }}</td>
                @endif
                @if ($this->columnVisible('locale'))
                    <td>{{ $template->locale }}</td>
                @endif
                @if ($this->columnVisible('school_id'))
                    <td>{{ $template->school_id ? __('School') : __('System default') }}</td>
                @endif
                @if ($this->columnVisible('is_active'))
                    <td>
                        @if ($template->is_active)
                            <span class="badge text-bg-success">{{ __('Active') }}</span>
                        @else
                            <span class="badge text-bg-secondary">{{ __('Inactive') }}</span>
                        @endif
                    </td>
                @endif
            </tr>
        @empty
            <tr>
                <td colspan="5" class="text-center text-body-secondary py-4">{{ __('No notification templates yet.') }}</td>
            </tr>
        @endforelse
    </x-data-table>
</div>
