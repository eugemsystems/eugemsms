<div>
    <div class="d-flex align-items-center gap-2 mb-4">
        <a href="{{ route('finance.fees.structures', $school) }}" class="btn btn-icon btn-outline-secondary btn-sm" wire:navigate>
            <i class="ri ri-arrow-left-line"></i>
        </a>
        <div>
            <h4 class="mb-1">{{ __('Versions of :name', ['name' => $structure->name]) }}</h4>
            <p class="text-body-secondary mb-0">{{ __('Every version is retained forever — it stays linked to the assignments it produced.') }}</p>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-6">
                    <div class="form-floating form-floating-outline">
                        <select class="form-select" id="compareLeftId" wire:model.live="compareLeftId">
                            @foreach ($versions as $version)
                                <option value="{{ $version->id }}">v{{ $version->version }} ({{ \Illuminate\Support\Str::headline($version->status) }})</option>
                            @endforeach
                        </select>
                        <label for="compareLeftId">{{ __('Compare') }}</label>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-floating form-floating-outline">
                        <select class="form-select" id="compareRightId" wire:model.live="compareRightId">
                            @foreach ($versions as $version)
                                <option value="{{ $version->id }}">v{{ $version->version }} ({{ \Illuminate\Support\Str::headline($version->status) }})</option>
                            @endforeach
                        </select>
                        <label for="compareRightId">{{ __('Against') }}</label>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @if ($left && $right)
        <div class="row g-3">
            @foreach ([$left, $right] as $version)
                <div class="col-lg-6">
                    <div class="card mb-3">
                        <div class="card-header"><h6 class="mb-0">v{{ $version->version }} — {{ \Illuminate\Support\Str::headline($version->status) }}</h6></div>
                        <div class="card-body">
                            <h6 class="small text-uppercase text-body-secondary">{{ __('Rules') }}</h6>
                            <ul class="list-unstyled small mb-3">
                                @forelse ($version->rules as $rule)
                                    <li wire:key="v{{ $version->id }}-rule-{{ $rule->id }}">{{ $rule->attribute }} {{ $rule->operator }} {{ is_array($rule->value) ? implode(',', $rule->value) : $rule->value }}</li>
                                @empty
                                    <li>{{ __('No rules.') }}</li>
                                @endforelse
                            </ul>
                            <h6 class="small text-uppercase text-body-secondary">{{ __('Items') }}</h6>
                            <ul class="list-unstyled small mb-0">
                                @forelse ($version->items as $item)
                                    <li wire:key="v{{ $version->id }}-item-{{ $item->id }}">
                                        {{ $item->component->code }} — {{ $item->billing_basis }}
                                        @if ($item->amount_minor !== null) {{ number_format($item->amount_minor / 100, 2) }} @endif
                                        @if ($item->unit_rate_minor !== null) @ {{ number_format($item->unit_rate_minor / 100, 2) }}/{{ $item->unit_label }} @endif
                                        {{ $item->currency }}
                                    </li>
                                @empty
                                    <li>{{ __('No items.') }}</li>
                                @endforelse
                            </ul>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</div>
