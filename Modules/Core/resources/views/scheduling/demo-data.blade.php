<div>
    <div class="mb-4">
        <h4 class="mb-1">{{ __('Demo data') }}</h4>
        <p class="text-body-secondary mb-0">{{ __('Seed a realistic demo dataset to test a module end to end, without setting everything up by hand.') }}</p>
    </div>

    <div class="card mb-3">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-4">
                    <div class="form-floating form-floating-outline">
                        <input type="text" class="form-control @error('code') is-invalid @enderror" id="code" wire:model="code" placeholder=" ">
                        <label for="code">{{ __('School code') }}</label>
                        @error('code') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="form-text">{{ __('The demo school this creates or reuses. Pick a new code to build a second, independent demo school.') }}</div>
                </div>
                <div class="col-md-3">
                    <div class="form-floating form-floating-outline">
                        <input type="number" class="form-control @error('students') is-invalid @enderror" id="students" wire:model="students" min="10" max="1000">
                        <label for="students">{{ __('Students to enrol') }}</label>
                        @error('students') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="form-text">{{ __('Only used by "School setup" and "Run all".') }}</div>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead>
                    <tr>
                        <th style="width: 16rem;">{{ __('Seeder') }}</th>
                        <th>{{ __('What it does') }}</th>
                        <th style="width: 8rem;"></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($seeders as $seeder)
                        <tr wire:key="seeder-{{ $seeder['key'] }}">
                            <td>
                                <div class="fw-semibold">{{ $seeder['name'] }}</div>
                                <div class="small text-body-secondary font-monospace">{{ $seeder['command'] }}</div>
                            </td>
                            <td class="small text-body-secondary">{{ $seeder['description'] }}</td>
                            <td class="text-end">
                                <button
                                    type="button"
                                    class="btn btn-sm {{ $seeder['key'] === 'finance-all' ? 'btn-primary' : 'btn-outline-primary' }}"
                                    wire:click="run('{{ $seeder['key'] }}')"
                                    wire:loading.attr="disabled"
                                    wire:target="run('{{ $seeder['key'] }}')"
                                >
                                    <span wire:loading.remove wire:target="run('{{ $seeder['key'] }}')">
                                        <i class="ri ri-play-line me-1"></i>{{ __('Run') }}
                                    </span>
                                    <span wire:loading wire:target="run('{{ $seeder['key'] }}')">
                                        <span class="spinner-border spinner-border-sm me-1" aria-hidden="true"></span>{{ __('Running…') }}
                                    </span>
                                </button>
                            </td>
                        </tr>

                        @if ($lastRanKey === $seeder['key'])
                            <tr>
                                <td colspan="3">
                                    <div class="alert {{ $lastRanOk ? 'alert-success' : 'alert-danger' }} mb-2">
                                        <div class="d-flex align-items-center gap-2 mb-2">
                                            <i class="ri {{ $lastRanOk ? 'ri-checkbox-circle-line' : 'ri-error-warning-line' }}"></i>
                                            <strong>{{ $lastRanOk ? __('Finished.') : __('Failed.') }}</strong>
                                        </div>
                                        @if ($lastOutput !== null && $lastOutput !== '')
                                            <pre class="mb-0 small" style="white-space: pre-wrap;">{{ $lastOutput }}</pre>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endif
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
