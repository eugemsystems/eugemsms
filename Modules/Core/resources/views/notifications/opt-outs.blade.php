<div>
    <div class="d-flex align-items-center gap-2 mb-4 flex-wrap">
        <a href="{{ route('notifications.log', $school) }}" class="btn btn-icon btn-outline-secondary btn-sm" wire:navigate>
            <i class="ri ri-arrow-left-line"></i>
        </a>
        <div class="flex-grow-1">
            <h4 class="mb-1">{{ __('Notification opt-outs') }}</h4>
            <p class="text-body-secondary mb-0">{{ __('Addresses that will never receive a marketing or general notice.') }}</p>
        </div>
        <button type="button" class="btn btn-primary" wire:click="openCreateModal">
            <i class="ri ri-add-line me-1"></i>{{ __('Add opt-out') }}
        </button>
    </div>

    <x-data-table
        :columns="$this->tableColumns()"
        :rows="$optOuts"
        :search="$search"
        :sort-column="$sortColumn"
        :sort-direction="$sortDirection"
        :per-page="$perPage"
        :column-filters="$columnFilters"
        :hidden-columns="$hiddenColumns"
        :per-page-options="$this->perPageOptions()"
        :with-actions="false"
    >
        @forelse ($optOuts as $optOut)
            <tr wire:key="optout-{{ $optOut->id }}">
                @if ($this->columnVisible('address'))
                    <td>{{ $optOut->address }}</td>
                @endif
                @if ($this->columnVisible('channel'))
                    <td>{{ \Illuminate\Support\Str::headline($optOut->channel) }}</td>
                @endif
                @if ($this->columnVisible('reason'))
                    <td>{{ $optOut->reason ? \Illuminate\Support\Str::headline($optOut->reason) : '—' }}</td>
                @endif
                @if ($this->columnVisible('opted_out_at'))
                    <td>{{ $optOut->opted_out_at->format('d M Y H:i') }}</td>
                @endif
            </tr>
        @empty
            <tr>
                <td colspan="4" class="text-center text-body-secondary py-4">{{ __('No opt-outs recorded.') }}</td>
            </tr>
        @endforelse
    </x-data-table>

    @if ($showCreateModal)
        <div class="modal show d-block" tabindex="-1" style="background: rgba(0, 0, 0, .5);">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <form wire:submit="create">
                        <div class="modal-header">
                            <h5 class="modal-title">{{ __('Add opt-out') }}</h5>
                            <button type="button" class="btn-close" wire:click="$set('showCreateModal', false)" aria-label="{{ __('Close') }}"></button>
                        </div>
                        <div class="modal-body">
                            <div class="form-floating form-floating-outline mb-3">
                                <input type="text" class="form-control @error('address') is-invalid @enderror" id="address" wire:model="address" placeholder=" ">
                                <label for="address">{{ __('Phone number or email') }}</label>
                                @error('address') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                            <div class="form-floating form-floating-outline">
                                <select class="form-select @error('channel') is-invalid @enderror" id="channel" wire:model="channel">
                                    <option value="sms">SMS</option>
                                    <option value="whatsapp">WhatsApp</option>
                                    <option value="email">{{ __('Email') }}</option>
                                    <option value="push">{{ __('Push') }}</option>
                                </select>
                                <label for="channel">{{ __('Channel') }}</label>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-outline-secondary" wire:click="$set('showCreateModal', false)">{{ __('Cancel') }}</button>
                            <button type="submit" class="btn btn-primary" wire:loading.attr="disabled">{{ __('Add') }}</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif
</div>
