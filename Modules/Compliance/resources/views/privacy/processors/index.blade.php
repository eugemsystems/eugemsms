<div>
    <h4 class="mb-1">{{ __('Third-party processors') }} 🇿🇼</h4>

    <div class="row g-4">
        <div class="col-lg-7">
            <div class="card">
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead><tr><th>{{ __('Name') }}</th><th>{{ __('Type') }}</th><th>{{ __('Country') }}</th><th>{{ __('Cross-border') }}</th></tr></thead>
                        <tbody>
                            @forelse ($processors as $processor)
                                <tr wire:key="proc-{{ $processor->id }}">
                                    <td>{{ $processor->name }}</td>
                                    <td>{{ str_replace('_', ' ', $processor->processor_type) }}</td>
                                    <td>{{ $processor->country ?? '—' }}</td>
                                    <td>{{ $processor->isCrossBorder() ? __('Yes ⭐') : __('No') }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="text-center text-body-secondary py-3">{{ __('No processors registered yet.') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="col-lg-5">
            <div class="card">
                <div class="card-header">{{ __('Register processor') }}</div>
                <div class="card-body">
                    <input type="text" class="form-control mb-2" wire:model="name" placeholder="{{ __('Name') }}">
                    <select class="form-select mb-2" wire:model="processorType">
                        <option value="payment_gateway">{{ __('Payment gateway') }}</option>
                        <option value="sms">{{ __('SMS') }}</option>
                        <option value="whatsapp">{{ __('WhatsApp') }}</option>
                        <option value="cloud_storage">{{ __('Cloud storage') }}</option>
                        <option value="email">{{ __('Email') }}</option>
                        <option value="analytics">{{ __('Analytics') }}</option>
                        <option value="backup">{{ __('Backup') }}</option>
                    </select>
                    <input type="text" class="form-control mb-2" wire:model="dataSharedText" placeholder="{{ __('Data shared, comma-separated') }}">
                    <textarea class="form-control mb-2" wire:model="purpose" placeholder="{{ __('Purpose') }}"></textarea>
                    <input type="text" class="form-control mb-2" wire:model="country" placeholder="{{ __('Country code, e.g. ZA (optional)') }}">
                    <button type="button" class="btn btn-primary btn-sm" wire:click="register">{{ __('Register') }}</button>
                </div>
            </div>
        </div>
    </div>
</div>
