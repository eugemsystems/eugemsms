<div>
    <div class="mb-4">
        <h4 class="mb-1">{{ __('Services') }}</h4>
        <p class="text-body-secondary mb-0">{{ __('Test optional services. Every test can be skipped — you can configure these later.') }}</p>
    </div>

    <div class="d-flex flex-column gap-2 mb-4">
        @foreach ($services as $service)
            <div class="border rounded p-3 d-flex align-items-center justify-content-between gap-3">
                <div>
                    <div class="fw-medium text-capitalize">{{ $service }}</div>
                    @if (isset($results[$service]))
                        <div class="small {{ $results[$service]['success'] ? 'text-success' : 'text-danger' }}">
                            {{ $results[$service]['message'] }}
                        </div>
                    @endif
                </div>
                <button type="button" class="btn btn-sm btn-outline-secondary flex-shrink-0" wire:click="test('{{ $service }}')">
                    {{ __('Test') }}
                </button>
            </div>
        @endforeach
    </div>

    <button type="button" class="btn btn-primary d-grid w-100" wire:click="continue">{{ __('Continue') }}</button>
</div>
