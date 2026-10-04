<div>
    <div class="mb-4">
        <h4 class="mb-1">{{ __('Webhook log') }}</h4>
        <p class="text-body-secondary mb-0">{{ __('Every webhook delivery, raw and unmodified — "replay" reprocesses a stored row against this platform, never redelivers anything to the gateway.') }}</p>
    </div>

    <x-data-table
        :columns="$this->tableColumns()"
        :rows="$webhooks"
        :search="$search"
        :sort-column="$sortColumn"
        :sort-direction="$sortDirection"
        :per-page="$perPage"
        :column-filters="$columnFilters"
        :hidden-columns="$hiddenColumns"
        :per-page-options="$this->perPageOptions()"
        :with-actions="true"
    >
        @forelse ($webhooks as $webhook)
            <tr wire:key="webhook-{{ $webhook->id }}">
                @if ($this->columnVisible('driver'))
                    <td>{{ $webhook->driver }}</td>
                @endif
                @if ($this->columnVisible('event_type'))
                    <td>{{ $webhook->event_type ?? '—' }}</td>
                @endif
                @if ($this->columnVisible('signature_valid'))
                    <td>
                        <span class="badge {{ $webhook->signature_valid ? 'text-bg-success' : 'text-bg-danger' }}">
                            {{ $webhook->signature_valid ? __('Valid') : __('Invalid') }}
                        </span>
                    </td>
                @endif
                @if ($this->columnVisible('processing_status'))
                    <td>
                        <span class="badge {{ $webhook->processing_status === 'processed' ? 'text-bg-success' : ($webhook->processing_status === 'failed' ? 'text-bg-danger' : 'text-bg-secondary') }}">
                            {{ ucfirst($webhook->processing_status) }}
                        </span>
                        @if ($webhook->processing_error)
                            <div class="text-danger small">{{ $webhook->processing_error }}</div>
                        @endif
                    </td>
                @endif
                @if ($this->columnVisible('received_at'))
                    <td>{{ $webhook->received_at->format('d M Y H:i') }}</td>
                @endif
                <td class="text-end">
                    <button type="button" class="btn btn-sm btn-outline-secondary" wire:click="viewPayload({{ $webhook->id }})">{{ __('View payload') }}</button>
                    @if ($webhook->processing_status === 'failed' && $webhook->signature_valid)
                        <button type="button" class="btn btn-sm btn-outline-warning" wire:click="reprocess({{ $webhook->id }})">{{ __('Reprocess') }}</button>
                    @endif
                </td>
            </tr>
        @empty
            <tr>
                <td colspan="6" class="text-center text-body-secondary py-4">{{ __('No webhooks match these filters.') }}</td>
            </tr>
        @endforelse
    </x-data-table>

    @if ($viewingWebhook)
        <div class="modal show d-block" tabindex="-1" style="background: rgba(0, 0, 0, .5);">
            <div class="modal-dialog modal-dialog-centered modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">{{ __('Raw webhook payload') }}</h5>
                        <button type="button" class="btn-close" wire:click="closePayload" aria-label="{{ __('Close') }}"></button>
                    </div>
                    <div class="modal-body">
                        <h6>{{ __('Headers') }}</h6>
                        <pre class="bg-light p-3 rounded small">{{ json_encode($viewingWebhook->raw_headers, JSON_PRETTY_PRINT) }}</pre>
                        <h6>{{ __('Body') }}</h6>
                        <pre class="bg-light p-3 rounded small">{{ $viewingWebhook->raw_payload }}</pre>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" wire:click="closePayload">{{ __('Close') }}</button>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
