<div>
    <div class="d-flex align-items-center gap-2 mb-4">
        <div class="flex-grow-1">
            <h4 class="mb-1">{{ __('Debtor workbench') }}</h4>
            <p class="text-body-secondary mb-0">{{ __('Highest balance first — every learner with something outstanding.') }}</p>
        </div>
        <div style="width: 10rem;">
            <select class="form-select form-select-sm" wire:model.live="currency">
                @foreach ($currencies as $currency)
                    <option value="{{ $currency->value }}">{{ $currency->value }}</option>
                @endforeach
            </select>
        </div>
    </div>

    <div class="card">
        <div class="table-responsive">
            <table class="table mb-0 align-middle">
                <thead>
                    <tr>
                        <th>{{ __('Learner') }}</th>
                        <th class="text-end">{{ __('Invoices') }}</th>
                        <th>{{ __('Oldest due') }}</th>
                        <th class="text-end">{{ __('Total balance') }}</th>
                        <th class="text-end">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($chaseList as $row)
                        <tr wire:key="chase-{{ $row['student']->id }}">
                            <td>
                                <a href="{{ route('finance.accounts.learner-account', ['school' => $school, 'student' => $row['student']]) }}" wire:navigate>
                                    {{ $row['student']->admission_number }} — {{ $row['student']->fullName() }}
                                </a>
                            </td>
                            <td class="text-end">{{ $row['invoice_count'] }}</td>
                            <td>{{ \Illuminate\Support\Carbon::parse($row['oldest_due_date'])->format('d M Y') }}</td>
                            <td class="text-end fw-semibold">{{ number_format($row['total_balance_minor'] / 100, 2) }} {{ $currency }}</td>
                            <td class="text-end">
                                <button type="button" class="btn btn-sm btn-outline-secondary" wire:click="openNoteModal({{ $row['student']->id }})">{{ __('Log call') }}</button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="text-center text-body-secondary py-4">{{ __('No outstanding debtors.') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @if ($notingStudentId !== null)
        <div class="modal show d-block" tabindex="-1" style="background: rgba(0, 0, 0, .5);">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <form wire:submit="saveNote">
                        <div class="modal-header">
                            <h5 class="modal-title">{{ __('Log a chase call') }}</h5>
                            <button type="button" class="btn-close" wire:click="$set('notingStudentId', null)" aria-label="{{ __('Close') }}"></button>
                        </div>
                        <div class="modal-body">
                            <div class="mb-3">
                                <div class="form-floating form-floating-outline">
                                    <select class="form-select" id="outcome" wire:model="outcome">
                                        <option value="promised_to_pay">{{ __('Promised to pay') }}</option>
                                        <option value="no_answer">{{ __('No answer') }}</option>
                                        <option value="disputed">{{ __('Disputed') }}</option>
                                        <option value="payment_plan_requested">{{ __('Payment plan requested') }}</option>
                                        <option value="unreachable">{{ __('Unreachable') }}</option>
                                        <option value="other">{{ __('Other') }}</option>
                                    </select>
                                    <label for="outcome">{{ __('Outcome') }}</label>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label" for="note">{{ __('Note') }}</label>
                                <textarea class="form-control @error('note') is-invalid @enderror" id="note" wire:model="note" rows="3"></textarea>
                                @error('note') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                            <div class="mb-3">
                                <div class="form-floating form-floating-outline">
                                    <input type="date" class="form-control" id="nextActionOn" wire:model="nextActionOn">
                                    <label for="nextActionOn">{{ __('Next action on (optional)') }}</label>
                                </div>
                            </div>
                            @if ($recentNotes->isNotEmpty())
                                <h6 class="small text-uppercase text-body-secondary">{{ __('Recent notes') }}</h6>
                                <ul class="list-unstyled small">
                                    @foreach ($recentNotes as $recent)
                                        <li wire:key="recent-{{ $recent->id }}" class="mb-1">
                                            <strong>{{ \Illuminate\Support\Str::headline($recent->outcome) }}</strong> — {{ $recent->note }}
                                            <span class="text-body-secondary">({{ $recent->created_at->format('d M Y') }})</span>
                                        </li>
                                    @endforeach
                                </ul>
                            @endif
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-outline-secondary" wire:click="$set('notingStudentId', null)">{{ __('Cancel') }}</button>
                            <button type="submit" class="btn btn-primary" wire:loading.attr="disabled">{{ __('Save note') }}</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif
</div>
