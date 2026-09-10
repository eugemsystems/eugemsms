<div>
    <div class="mb-4">
        <h4 class="mb-1">{{ __('Requirements') }}</h4>
        <p class="text-body-secondary mb-0">{{ __('Checking this server against the sERP installer requirements.') }}</p>
    </div>

    <div class="table-responsive mb-4">
        <table class="table table-sm align-middle">
            <thead>
                <tr>
                    <th>{{ __('Check') }}</th>
                    <th>{{ __('Status') }}</th>
                    <th>{{ __('Message') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($checks as $check)
                    <tr>
                        <td>
                            {{ $check['name'] }}
                            @if ($check['mandatory'])
                                <span class="badge text-bg-secondary ms-1">{{ __('required') }}</span>
                            @endif
                        </td>
                        <td>
                            @if ($check['status'] === 'pass')
                                <span class="badge text-bg-success">{{ __('Pass') }}</span>
                            @elseif ($check['status'] === 'warn')
                                <span class="badge text-bg-warning">{{ __('Warn') }}</span>
                            @else
                                <span class="badge text-bg-danger">{{ __('Fail') }}</span>
                            @endif
                        </td>
                        <td class="small text-body-secondary">
                            {{ $check['message'] }}
                            @if ($check['status'] !== 'pass' && $check['remediation'])
                                <div>{{ $check['remediation'] }}</div>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    @unless ($passesMandatory)
        <div class="alert alert-danger d-flex align-items-center gap-2">
            <i class="ri ri-error-warning-line"></i>
            {{ __('Mandatory requirements are not met. Fix the items above and re-check.') }}
        </div>
    @endunless

    <div class="d-flex gap-2">
        <button type="button" class="btn btn-outline-secondary d-inline-flex align-items-center gap-1" wire:click="runCheck">
            <i class="ri ri-refresh-line"></i> {{ __('Re-check') }}
        </button>
        <button type="button" class="btn btn-primary flex-fill" wire:click="continue" @disabled(! $passesMandatory)>
            {{ __('Continue') }}
        </button>
    </div>
</div>
