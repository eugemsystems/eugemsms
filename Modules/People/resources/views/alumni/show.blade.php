<div>
    <div class="d-flex align-items-center gap-2 mb-4">
        <a href="{{ route('alumni.directory.index', $school) }}" class="btn btn-sm btn-outline-secondary" wire:navigate><i class="ri ri-arrow-left-line"></i></a>
        <div><h4 class="mb-0">{{ $student?->fullName() }} <small class="text-body-secondary">{{ __('Class of :year', ['year' => $alumnus->graduation_year]) }}</small></h4><p class="text-body-secondary small mb-0">{{ $alumnus->admission_number }} · {{ $alumnus->finalGradeLevel?->name }}@if ($alumnus->finalHouse) · {{ $alumnus->finalHouse->name }} @endif</p></div>
        @if ($alumnus->status === 'opted_out') <span class="badge text-bg-danger ms-auto">{{ __('Do not contact') }}</span> @endif
    </div>
    <div class="row g-4">
        <div class="col-lg-7">
            <div class="card mb-4">
                <div class="card-header">{{ __('Academic summary — frozen at graduation') }}</div>
                <div class="card-body small">
                    <div class="mb-2">{{ __('Final average') }}: <strong>{{ $snapshot['final_average_percent'] ?? '—' }}@if (($snapshot['final_average_percent'] ?? null) !== null)% @endif</strong></div>
                    <div class="table-responsive"><table class="table table-sm mb-3">
                        <thead><tr><th>{{ __('Term') }}</th><th class="text-end">{{ __('Average') }}</th><th class="text-end">{{ __('Class position') }}</th><th class="text-end">{{ __('Subjects passed') }}</th></tr></thead>
                        <tbody>@forelse ($snapshot['terms'] ?? [] as $row)<tr><td>{{ $termNames[$row['term_id']] ?? $row['term_id'] }}</td><td class="text-end">{{ $row['average_percent'] ?? '—' }}</td><td class="text-end">{{ $row['class_position'] ? $row['class_position'].'/'.$row['class_size'] : '—' }}</td><td class="text-end">{{ $row['subjects_passed'] ?? '—' }}/{{ $row['subjects_taken'] ?? '—' }}</td></tr>@empty<tr><td colspan="4" class="text-body-secondary">{{ __('No results were recorded.') }}</td></tr>@endforelse</tbody>
                    </table></div>
                    <div class="fw-semibold mb-1">{{ __('Honours') }}</div>
                    <ul class="mb-0">@forelse ($snapshot['honours'] ?? [] as $honour)<li>{{ $honour['title'] }} <span class="text-body-secondary">({{ $honour['award_type'] }}, {{ $honour['awarded_on'] }})</span></li>@empty<li class="text-body-secondary">{{ __('None recorded.') }}</li>@endforelse</ul>
                </div>
            </div>
            <div class="card">
                <div class="card-header">{{ __('Career & further education') }}</div>
                <ul class="list-group list-group-flush small">
                    @forelse ($updates as $update)
                        <li class="list-group-item d-flex justify-content-between align-items-start" wire:key="cu-{{ $update->id }}">
                            <span><strong>{{ $update->title }}</strong> <span class="text-body-secondary">{{ $update->institution_or_employer }}</span><br><span class="text-body-secondary">{{ ucfirst($update->update_type) }}@if ($update->is_current) · {{ __('current') }} @endif</span></span>
                            <span class="text-nowrap">@if ($update->verified) <span class="badge text-bg-success">{{ __('Confirmed') }}</span> @else <span class="badge text-bg-light border">{{ __('Unverified') }}</span> @if ($canVerify) <button type="button" class="btn btn-sm btn-outline-primary" wire:click="verify({{ $update->id }})">{{ __('Confirm') }}</button> @endif @endif</span>
                        </li>
                    @empty
                        <li class="list-group-item text-body-secondary">{{ __('No updates yet.') }}</li>
                    @endforelse
                </ul>
                @if ($canVerify)
                    <div class="card-footer">
                        <div class="row g-2">
                            <div class="col-md-3"><select class="form-select form-select-sm" wire:model="updateType"><option value="employment">{{ __('Employment') }}</option><option value="education">{{ __('Education') }}</option><option value="achievement">{{ __('Achievement') }}</option></select></div>
                            <div class="col-md-4"><input type="text" class="form-control form-control-sm" wire:model="title" placeholder="{{ __('Title') }}"></div>
                            <div class="col-md-3"><input type="text" class="form-control form-control-sm" wire:model="institution" placeholder="{{ __('Employer / institution') }}"></div>
                            <div class="col-md-2"><button type="button" class="btn btn-sm btn-primary" wire:click="addUpdate">{{ __('Add') }}</button></div>
                        </div>
                        @error('title') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                    </div>
                @endif
            </div>
        </div>
        <div class="col-lg-5">
            <div class="card mb-4">
                <div class="card-header">{{ __('Giving history') }}</div>
                <ul class="list-group list-group-flush small">
                    @forelse ($pledges as $pledge)
                        <li class="list-group-item" wire:key="pl-{{ $pledge->id }}">{{ number_format($pledge->pledged_amount_minor / 100, 2) }} {{ $pledge->currency }} {{ __('pledged') }} · <strong>{{ number_format($pledge->paid_to_date_minor / 100, 2) }}</strong> {{ __('received') }} <span class="text-body-secondary">({{ $pledge->status }})</span></li>
                    @empty
                        <li class="list-group-item text-body-secondary">{{ __('No pledges.') }}</li>
                    @endforelse
                    @foreach ($donations as $donation) <li class="list-group-item text-body-secondary" wire:key="dn-{{ $donation->id }}">{{ $donation->received_at?->toFormattedDateString() }} — {{ number_format($donation->amount_minor / 100, 2) }} {{ $donation->currency }}</li> @endforeach
                </ul>
            </div>
            @if ($canManageContact)
            <div class="card">
                <div class="card-header">{{ __('Contact & portal') }}</div>
                <div class="card-body small">
                    @if ($alumnus->status !== 'opted_out')
                        <input type="text" class="form-control form-control-sm mb-2" wire:model="optOutReason" placeholder="{{ __('Reason (optional)') }}">
                        <button type="button" class="btn btn-sm btn-outline-danger" wire:click="optOut" wire:confirm="{{ __('Record an opt-out? All outreach stops immediately.') }}">{{ __('Record opt-out') }}</button>
                    @else
                        <div class="text-body-secondary">{{ __('Opted out. Only the alumnus can opt back in.') }}</div>
                    @endif
                    <hr>
                    @if ($alumnus->user_id)
                        <div class="text-body-secondary">{{ __('A portal account exists.') }}</div>
                    @else
                        <input type="email" class="form-control form-control-sm mb-2" wire:model="portalEmail" placeholder="{{ __('Their email address') }}">
                        @error('portalEmail') <div class="text-danger small mb-2">{{ $message }}</div> @enderror
                        <button type="button" class="btn btn-sm btn-outline-primary" wire:click="offerPortal">{{ __('Offer a portal account') }}</button>
                    @endif
                </div>
            </div>
            @endif
        </div>
    </div>
</div>
