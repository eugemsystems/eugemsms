<div>
    <div class="d-flex align-items-center gap-2 mb-4">
        <div class="flex-grow-1">
            <h4 class="mb-1">{{ __('Payment plans') }}</h4>
            <p class="text-body-secondary mb-0">{{ __('An instalment schedule against a guardian\'s total balance.') }}</p>
        </div>
        <button type="button" class="btn btn-primary" wire:click="openCreateModal">
            <i class="ri ri-add-line me-1"></i>{{ __('New plan') }}
        </button>
    </div>

    <x-data-table
        :columns="$this->tableColumns()"
        :rows="$plans"
        :search="$search"
        :sort-column="$sortColumn"
        :sort-direction="$sortDirection"
        :per-page="$perPage"
        :column-filters="$columnFilters"
        :hidden-columns="$hiddenColumns"
        :per-page-options="$this->perPageOptions()"
    >
        @forelse ($plans as $plan)
            <tr wire:key="plan-{{ $plan->id }}">
                @if ($this->columnVisible('total_minor'))
                    <td>
                        {{ number_format($plan->total_minor / 100, 2) }} {{ $plan->currency }}
                        <div class="text-body-secondary small">{{ $plan->student->admission_number }} — {{ $plan->student->fullName() }}</div>
                    </td>
                @endif
                @if ($this->columnVisible('instalment_count'))
                    <td>{{ $plan->instalment_count }}</td>
                @endif
                @if ($this->columnVisible('status'))
                    <td>
                        <span class="badge {{ match ($plan->status) { 'active' => 'text-bg-info', 'completed' => 'text-bg-success', 'breached' => 'text-bg-danger', 'cancelled' => 'text-bg-secondary', default => 'text-bg-warning' } }}">
                            {{ \Illuminate\Support\Str::headline($plan->status) }}
                        </span>
                    </td>
                @endif
                <td class="text-end">
                    <div class="d-flex justify-content-end gap-1">
                        <button type="button" class="btn btn-sm btn-outline-secondary" wire:click="toggleExpand({{ $plan->id }})">{{ __('Instalments') }}</button>
                        @if ($plan->status === 'proposed')
                            <button type="button" class="btn btn-sm btn-success" wire:click="approve({{ $plan->id }})">{{ __('Approve') }}</button>
                        @endif
                        @if (in_array($plan->status, ['proposed', 'active', 'breached']))
                            <button type="button" class="btn btn-sm btn-outline-danger" wire:click="cancel({{ $plan->id }})" wire:confirm="{{ __('Cancel this plan?') }}">{{ __('Cancel') }}</button>
                        @endif
                    </div>
                </td>
            </tr>
            @if ($expandedPlanId === $plan->id)
                <tr wire:key="plan-{{ $plan->id }}-instalments">
                    <td colspan="4" class="bg-body-tertiary">
                        <table class="table table-sm mb-0">
                            <thead>
                                <tr>
                                    <th>{{ __('#') }}</th>
                                    <th>{{ __('Due') }}</th>
                                    <th class="text-end">{{ __('Amount') }}</th>
                                    <th class="text-end">{{ __('Paid') }}</th>
                                    <th>{{ __('Status') }}</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($plan->instalments as $instalment)
                                    <tr wire:key="instalment-{{ $instalment->id }}">
                                        <td>{{ $instalment->instalment_number }}</td>
                                        <td>{{ $instalment->due_date->format('d M Y') }}</td>
                                        <td class="text-end">{{ number_format($instalment->amount_minor / 100, 2) }}</td>
                                        <td class="text-end">{{ number_format($instalment->paid_minor / 100, 2) }}</td>
                                        <td>{{ \Illuminate\Support\Str::headline($instalment->status) }}</td>
                                        <td>
                                            @if ($instalment->status !== 'paid')
                                                <button type="button" class="btn btn-sm btn-link p-0" wire:click="recordInstalmentPayment({{ $instalment->id }}, '{{ number_format(($instalment->amount_minor - $instalment->paid_minor) / 100, 2, '.', '') }}')" wire:confirm="{{ __('Record the full remaining instalment as paid?') }}">
                                                    {{ __('Record as paid') }}
                                                </button>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </td>
                </tr>
            @endif
        @empty
            <tr>
                <td colspan="4" class="text-center text-body-secondary py-4">{{ __('No payment plans yet.') }}</td>
            </tr>
        @endforelse
    </x-data-table>

    @if ($showCreateModal)
        <div class="modal show d-block" tabindex="-1" style="background: rgba(0, 0, 0, .5);">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <form wire:submit="create">
                        <div class="modal-header">
                            <h5 class="modal-title">{{ __('New payment plan') }}</h5>
                            <button type="button" class="btn-close" wire:click="$set('showCreateModal', false)" aria-label="{{ __('Close') }}"></button>
                        </div>
                        <div class="modal-body">
                            <div class="mb-3">
                                <label class="form-label">{{ __('Learner') }}</label>
                                @if ($selectedStudentId !== null)
                                    <div class="input-group">
                                        <input type="text" class="form-control" value="{{ $selectedStudentLabel }}" disabled>
                                        <button type="button" class="btn btn-outline-secondary" wire:click="$set('selectedStudentId', null)">{{ __('Change') }}</button>
                                    </div>
                                @else
                                    <input type="text" class="form-control" wire:model.live.debounce.300ms="studentSearch" placeholder="{{ __('Search…') }}">
                                    @if ($studentSearch !== '')
                                        <div class="list-group mt-1">
                                            @forelse ($this->studentResults() as $result)
                                                <button type="button" wire:key="pp-result-{{ $result->id }}" class="list-group-item list-group-item-action" wire:click="selectStudent({{ $result->id }})">
                                                    {{ $result->admission_number }} — {{ $result->fullName() }}
                                                </button>
                                            @empty
                                                <div class="list-group-item text-body-secondary">{{ __('No matches.') }}</div>
                                            @endforelse
                                        </div>
                                    @endif
                                @endif
                            </div>
                            <div class="mb-3">
                                <div class="form-floating form-floating-outline">
                                    <select class="form-select @error('guardianId') is-invalid @enderror" id="pp-guardian" wire:model="guardianId">
                                        <option value="">{{ __('Select a guardian') }}</option>
                                        @foreach ($guardians as $guardian)
                                            <option value="{{ $guardian->id }}">{{ $guardian->displayName() }}</option>
                                        @endforeach
                                    </select>
                                    <label for="pp-guardian">{{ __('Responsible guardian') }}</label>
                                    @error('guardianId') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>
                            </div>
                            <div class="row g-3">
                                <div class="col-4">
                                    <div class="form-floating form-floating-outline">
                                        <input type="text" inputmode="decimal" class="form-control @error('totalAmount') is-invalid @enderror" id="pp-total" wire:model="totalAmount" placeholder=" ">
                                        <label for="pp-total">{{ __('Total') }}</label>
                                        @error('totalAmount') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                    </div>
                                </div>
                                <div class="col-4">
                                    <div class="form-floating form-floating-outline">
                                        <select class="form-select" id="pp-currency" wire:model="currency">
                                            <option value="USD">USD</option>
                                            <option value="ZWG">ZWG</option>
                                        </select>
                                        <label for="pp-currency">{{ __('Currency') }}</label>
                                    </div>
                                </div>
                                <div class="col-4">
                                    <div class="form-floating form-floating-outline">
                                        <input type="number" min="2" max="12" class="form-control" id="pp-count" wire:model="instalmentCount" placeholder=" ">
                                        <label for="pp-count">{{ __('Instalments') }}</label>
                                    </div>
                                </div>
                                <div class="col-12">
                                    <div class="form-floating form-floating-outline">
                                        <input type="date" class="form-control" id="pp-first-due" wire:model="firstDueDate">
                                        <label for="pp-first-due">{{ __('First instalment due') }}</label>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-outline-secondary" wire:click="$set('showCreateModal', false)">{{ __('Cancel') }}</button>
                            <button type="submit" class="btn btn-primary" wire:loading.attr="disabled">{{ __('Propose plan') }}</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif
</div>
