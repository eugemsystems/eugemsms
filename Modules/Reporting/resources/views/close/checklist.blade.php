<div>
    <h4 class="mb-1">{{ __('Close checklist') }} ⭐</h4>
    <p class="text-body-secondary small">{{ __('A blocking failure cannot be overridden by any user. A warning requires a written reason to acknowledge.') }}</p>

    <div class="row g-2 mb-3 align-items-end" style="max-width:36rem">
        <div class="col-5">
            <select class="form-select" wire:model="termId">
                <option value="">{{ __('Term') }}</option>
                @foreach ($terms as $term)
                    <option value="{{ $term->id }}">{{ $term->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-4">
            <select class="form-select" wire:model="periodType">
                <option value="financial">{{ __('Financial') }}</option>
                <option value="academic">{{ __('Academic') }}</option>
            </select>
        </div>
        <div class="col-3"><button type="button" class="btn btn-primary w-100" wire:click="run">{{ __('Run checklist') }}</button></div>
    </div>

    <div class="row g-4">
        <div class="col-lg-4">
            <div class="card">
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead><tr><th>{{ __('Run') }}</th><th>{{ __('Status') }}</th><th></th></tr></thead>
                        <tbody>
                            @forelse ($checklists as $checklist)
                                <tr wire:key="checklist-{{ $checklist->id }}" class="{{ $selected?->id === $checklist->id ? 'table-active' : '' }}">
                                    <td>{{ $checklist->run_at->toDateTimeString() }}</td>
                                    <td><span class="badge {{ $checklist->overall_status === 'failed' ? 'bg-danger' : ($checklist->overall_status === 'passed' ? 'bg-success' : 'bg-warning text-dark') }}">{{ $checklist->overall_status }}</span></td>
                                    <td><button type="button" class="btn btn-outline-secondary btn-sm" wire:click="$set('selectedChecklistId', {{ $checklist->id }})">{{ __('Open') }}</button></td>
                                </tr>
                            @empty
                                <tr><td colspan="3" class="text-center text-body-secondary py-3">{{ __('No runs yet.') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="col-lg-8">
            @if ($selected)
                <div class="card mb-3">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <span>{{ __('Blocking failures') }}: {{ $selected->blocking_failures }} — {{ __('Warnings') }}: {{ $selected->warnings }}</span>
                        <button type="button" class="btn btn-outline-primary btn-sm" wire:click="generatePack">{{ __('Generate close pack') }}</button>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-sm mb-0">
                            <thead><tr><th>{{ __('Check') }}</th><th>{{ __('Result') }}</th><th>{{ __('Message') }}</th><th></th></tr></thead>
                            <tbody>
                                @foreach ((array) $selected->results as $check)
                                    <tr wire:key="check-{{ $check['code'] }}">
                                        <td>{{ $check['label'] }}</td>
                                        <td>
                                            @if ($check['passed'])
                                                <span class="badge bg-success">{{ __('passed') }}</span>
                                            @else
                                                <span class="badge {{ $check['blocking'] ? 'bg-danger' : 'bg-warning text-dark' }}">{{ $check['blocking'] ? __('blocking') : __('warning') }}</span>
                                            @endif
                                        </td>
                                        <td class="small">{{ $check['message'] }}</td>
                                        <td>
                                            @if (! $check['passed'] && ! $check['blocking'])
                                                <button type="button" class="btn btn-outline-warning btn-sm" wire:click="startAcknowledge('{{ $check['code'] }}')">{{ __('Acknowledge') }}</button>
                                            @endif
                                        </td>
                                    </tr>
                                    @if ($acknowledgeCheckKey === $check['code'])
                                        <tr>
                                            <td colspan="4">
                                                <input type="text" class="form-control form-control-sm d-inline-block mb-0" style="width:auto" wire:model="acknowledgeReason" placeholder="{{ __('Written reason (required)') }}">
                                                <button type="button" class="btn btn-sm btn-warning" wire:click="acknowledge">{{ __('Save') }}</button>
                                            </td>
                                        </tr>
                                    @endif
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @else
                <div class="card"><div class="card-body text-body-secondary">{{ __('Select or run a checklist.') }}</div></div>
            @endif
        </div>
    </div>
</div>
