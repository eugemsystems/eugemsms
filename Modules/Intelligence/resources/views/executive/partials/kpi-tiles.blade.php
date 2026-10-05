<div class="row g-3">
    @forelse ($tiles as $tile)
        @php
            $colour = ['green' => 'success', 'amber' => 'warning', 'red' => 'danger'][$tile['status']] ?? 'secondary';
            $words = ['green' => __('on target'), 'amber' => __('watch'), 'red' => __('off target')][$tile['status']] ?? $tile['status'];
        @endphp
        <div class="col-md-6 col-xl-4" wire:key="kpi-{{ $tile['key'] }}">
            <div class="card border-{{ $colour }}">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div class="small text-body-secondary">{{ $tile['label'] }}</div>
                        <span class="badge bg-label-{{ $colour }}">{{ $words }}</span>
                    </div>
                    <div class="h3 mb-0 mt-1">{{ rtrim(rtrim(number_format($tile['current'], 2), '0'), '.') }} <small class="text-body-secondary">{{ $tile['unit'] }}</small></div>
                    <div class="small text-body-secondary">
                        {{ $tile['target'] !== null ? __('Target :t :unit (:dir)', ['t' => rtrim(rtrim(number_format($tile['target'], 2), '0'), '.'), 'unit' => $tile['unit'], 'dir' => $tile['higherIsBetter'] ? __('higher is better') : __('lower is better')]) : __('No target set') }}
                    </div>
                    @if ($tile['url']) <a href="{{ $tile['url'] }}" class="small" wire:navigate>{{ __('Details') }} →</a> @endif
                </div>
            </div>
        </div>
    @empty
        <div class="col-12 text-body-secondary small">{{ __('No indicators to show — there is no current academic year, or none are registered.') }}</div>
    @endforelse
</div>
