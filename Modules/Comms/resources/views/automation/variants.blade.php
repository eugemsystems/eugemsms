<div>
    <div class="d-flex align-items-center gap-2 mb-4">
        <a href="{{ route('comms.automation.index', $school) }}" class="btn btn-icon btn-outline-secondary btn-sm" wire:navigate>
            <i class="ri ri-arrow-left-line"></i>
        </a>
        <div>
            <h4 class="mb-0">{{ __('A/B performance') }}</h4>
            <p class="text-body-secondary small mb-0">{{ __('Variants split by weight, the same subject always getting the same variant. Compare them and retire the loser.') }}</p>
        </div>
    </div>

    <select class="form-select form-select-sm mb-3 w-auto" wire:model.live="ruleId">
        <option value="">{{ __('All rules') }}</option>
        @foreach ($rules as $rule) <option value="{{ $rule->id }}">{{ $rule->name }}</option> @endforeach
    </select>

    <div class="row g-4">
        <div class="col-lg-8">
            <div class="card">
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead><tr><th>{{ __('Rule') }}</th><th>{{ __('Variant') }}</th><th>{{ __('Template') }}</th><th class="text-end">{{ __('Weight') }}</th><th class="text-end">{{ __('Sent') }}</th><th class="text-end">{{ __('Opened') }}</th><th class="text-end">{{ __('Responded') }}</th></tr></thead>
                        <tbody>
                            @forelse ($variants as $variant)
                                <tr wire:key="var-{{ $variant->id }}">
                                    <td>{{ $ruleNames[$variant->rule_id] ?? '' }}</td>
                                    <td>{{ $variant->variant_key }}</td>
                                    <td class="small">{{ $variant->template_key }}</td>
                                    <td class="text-end">{{ $variant->weight_percent }}%</td>
                                    <td class="text-end">{{ $variant->sent_count }}</td>
                                    <td class="text-end">{{ $variant->sent_count > 0 ? $variant->opened_count.' ('.round($variant->opened_count / $variant->sent_count * 100).'%)' : '—' }}</td>
                                    <td class="text-end">{{ $variant->sent_count > 0 ? $variant->response_count.' ('.round($variant->response_count / $variant->sent_count * 100).'%)' : '—' }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="7" class="text-center text-body-secondary py-3">{{ __('No variants yet.') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        @if ($canManage)
            <div class="col-lg-4">
                <div class="card">
                    <div class="card-header">{{ __('Add variant') }}</div>
                    <div class="card-body">
                        <select class="form-select mb-2" wire:model="ruleId">
                            <option value="">{{ __('Rule…') }}</option>
                            @foreach ($rules as $rule) <option value="{{ $rule->id }}">{{ $rule->name }}</option> @endforeach
                        </select>
                        @error('ruleId') <div class="text-danger small mb-2">{{ $message }}</div> @enderror
                        <input type="text" class="form-control mb-2" wire:model="variantKey" placeholder="{{ __('Variant key, e.g. B') }}">
                        @error('variantKey') <div class="text-danger small mb-2">{{ $message }}</div> @enderror
                        <select class="form-select mb-2" wire:model="templateKey">
                            <option value="">{{ __('Template (notification key)…') }}</option>
                            @foreach ($notificationKeys as $key) <option value="{{ $key }}">{{ $key }}</option> @endforeach
                        </select>
                        @error('templateKey') <div class="text-danger small mb-2">{{ $message }}</div> @enderror
                        <label class="form-label small mb-0">{{ __('Weight (%)') }}</label>
                        <input type="number" class="form-control mb-2" wire:model="weightPercent" min="1" max="100">
                        @error('weightPercent') <div class="text-danger small mb-2">{{ $message }}</div> @enderror
                        <button type="button" class="btn btn-primary btn-sm" wire:click="addVariant">{{ __('Add variant') }}</button>
                    </div>
                </div>
            </div>
        @endif
    </div>
</div>
