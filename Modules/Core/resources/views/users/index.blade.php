<div>
    <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
        <div>
            <h4 class="mb-1">{{ __('Users') }}</h4>
            <p class="text-body-secondary mb-0">{{ __('Every login identity in your tenant.') }}</p>
        </div>
        <a href="{{ route('users.create') }}" class="btn btn-primary" wire:navigate>
            <i class="ri ri-add-line me-1"></i>{{ __('New user') }}
        </a>
    </div>

    <x-data-table
        :columns="$this->tableColumns()"
        :rows="$users"
        :search="$search"
        :sort-column="$sortColumn"
        :sort-direction="$sortDirection"
        :per-page="$perPage"
        :column-filters="$columnFilters"
        :hidden-columns="$hiddenColumns"
        :per-page-options="$this->perPageOptions()"
    >
        @forelse ($users as $user)
            <tr wire:key="user-{{ $user->id }}">
                @if ($this->columnVisible('first_name'))
                    <td>
                        <div class="fw-medium">{{ $user->first_name }} {{ $user->last_name }}</div>
                        @if ($user->other_names)
                            <div class="small text-body-secondary">{{ $user->other_names }}</div>
                        @endif
                    </td>
                @endif
                @if ($this->columnVisible('email'))
                    <td>{{ $user->email }}</td>
                @endif
                @if ($this->columnVisible('phone'))
                    <td>{{ $user->phone }}</td>
                @endif
                @if ($this->columnVisible('username'))
                    <td>{{ $user->username }}</td>
                @endif
                @if ($this->columnVisible('user_type'))
                    <td><span class="badge text-bg-light text-capitalize">{{ $user->user_type->value }}</span></td>
                @endif
                @if ($this->columnVisible('status'))
                    <td>
                        <span @class([
                            'badge text-capitalize',
                            'text-bg-success' => $user->status->value === 'active',
                            'text-bg-secondary' => $user->status->value === 'inactive',
                            'text-bg-warning' => $user->status->value === 'suspended',
                            'text-bg-danger' => $user->status->value === 'locked',
                            'text-bg-info' => $user->status->value === 'pending',
                        ])>{{ $user->status->value }}</span>
                    </td>
                @endif
                @if ($this->columnVisible('last_login_at'))
                    <td>{{ $user->last_login_at?->diffForHumans() ?? __('Never') }}</td>
                @endif
                <td class="text-end">
                    <div class="d-flex justify-content-end gap-1">
                        <a href="{{ route('users.show', $user) }}" class="btn btn-icon btn-sm btn-outline-secondary" wire:navigate title="{{ __('View') }}" aria-label="{{ __('View') }}">
                            <i class="icon-base ri ri-eye-line icon-22px"></i>
                        </a>
                        <a href="{{ route('users.edit', $user) }}" class="btn btn-icon btn-sm btn-outline-primary" wire:navigate title="{{ __('Edit') }}" aria-label="{{ __('Edit') }}">
                            <i class="icon-base ri ri-edit-line icon-22px"></i>
                        </a>
                        @if ($user->status->value === 'active')
                            <button type="button" class="btn btn-icon btn-sm btn-outline-warning" wire:click="deactivate({{ $user->id }})" wire:confirm="{{ __('Deactivate :name? They will be immediately signed out everywhere.', ['name' => $user->first_name.' '.$user->last_name]) }}" title="{{ __('Deactivate') }}" aria-label="{{ __('Deactivate') }}">
                                <i class="icon-base ri ri-forbid-line icon-22px"></i>
                            </button>
                        @endif
                    </div>
                </td>
            </tr>
        @empty
            <tr>
                <td colspan="8" class="text-center text-body-secondary py-4">
                    {{ __('No users yet.') }}
                </td>
            </tr>
        @endforelse
    </x-data-table>
</div>
