<div>
    <h4 class="mb-1">{{ __('Fiscalisation reconciliation') }} ⭐</h4>
    <p class="text-body-secondary small">{{ __('Any fiscalisable receipt with no accepted fiscal receipt after the configured window blocks financial period close.') }}</p>

    <button type="button" class="btn btn-primary btn-sm mb-3" wire:click="reconcile">{{ __('Run reconciliation now') }}</button>

    @if ($checked)
        <div class="alert {{ count($unreconciled) > 0 ? 'alert-danger' : 'alert-success' }}">
            {{ __('Unreconciled:') }} {{ count($unreconciled) }}
        </div>

        @if (count($unreconciled) > 0)
            <div class="card">
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead><tr><th>{{ __('Invoice #') }}</th><th>{{ __('Status') }}</th><th>{{ __('Source') }}</th></tr></thead>
                        <tbody>
                            @foreach ($unreconciled as $receipt)
                                <tr wire:key="unreconciled-{{ $receipt->id }}">
                                    <td>{{ $receipt->invoice_number }}</td>
                                    <td><span class="badge bg-danger">{{ $receipt->status }}</span></td>
                                    <td>{{ $receipt->source_type }} #{{ $receipt->source_id }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif
    @endif
</div>
