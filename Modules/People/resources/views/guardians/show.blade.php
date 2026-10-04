<div>
    <div class="d-flex align-items-center gap-2 mb-4">
        <div class="flex-grow-1">
            <h4 class="mb-1">{{ $guardian->displayName() }}</h4>
            <p class="text-body-secondary mb-0">
                {{ ucfirst($guardian->guardian_type) }}
                @if ($guardian->guardian_type === 'organisation' && $guardian->organisation_type) — {{ ucfirst($guardian->organisation_type) }} @endif
                · <span class="badge {{ $guardian->status === 'active' ? 'text-bg-success' : 'text-bg-secondary' }}">{{ ucfirst($guardian->status) }}</span>
            </p>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-md-5">
            <div class="card">
                <div class="card-header">{{ __('Contact details') }}</div>
                <div class="card-body">
                    <dl class="row mb-0">
                        <dt class="col-5">{{ __('Phone') }}</dt><dd class="col-7">{{ $guardian->primary_phone ?? '—' }}</dd>
                        <dt class="col-5">{{ __('Email') }}</dt><dd class="col-7">{{ $guardian->email ?? '—' }}</dd>
                        <dt class="col-5">{{ __('Country') }}</dt><dd class="col-7">{{ $guardian->country }}</dd>
                        <dt class="col-5">{{ __('Preferred language') }}</dt><dd class="col-7">{{ $guardian->preferred_language }}</dd>
                        <dt class="col-5">{{ __('Preferred channel') }}</dt><dd class="col-7">{{ ucfirst($guardian->preferred_channel) }}</dd>
                    </dl>
                </div>
            </div>
        </div>
        <div class="col-md-7">
            <div class="card">
                <div class="card-header">{{ __('Linked learners') }}</div>
                <p class="text-body-secondary small px-3 pt-3 mb-0">{{ __('To add or change a relationship, open the learner\'s own "Manage guardians" screen.') }}</p>
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead><tr><th>{{ __('Learner') }}</th><th>{{ __('Relationship') }}</th><th>{{ __('Roles') }}</th><th>{{ __('Status') }}</th><th></th></tr></thead>
                        <tbody>
                            @forelse ($studentLinks as $link)
                                <tr>
                                    <td>{{ $link->student?->fullName() }}</td>
                                    <td>{{ ucfirst($link->relationship) }}</td>
                                    <td>
                                        @if ($link->is_primary_contact) <span class="badge text-bg-primary">{{ __('Primary') }}</span> @endif
                                        @if ($link->is_fee_responsible) <span class="badge text-bg-success">{{ __('Fee responsible') }}</span> @endif
                                        @if ($link->is_emergency_contact) <span class="badge text-bg-warning">{{ __('Emergency') }}</span> @endif
                                        @if ($link->has_court_restriction) <span class="badge text-bg-danger">{{ __('Court restriction') }}</span> @endif
                                    </td>
                                    <td><span class="badge {{ $link->status === 'active' ? 'text-bg-success' : 'text-bg-secondary' }}">{{ ucfirst($link->status) }}</span></td>
                                    <td class="text-end">
                                        @if ($link->student)
                                            <a href="{{ route('people.students.guardians', [$school, $link->student]) }}" class="btn btn-sm btn-outline-secondary" wire:navigate>{{ __('Manage') }}</a>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="text-center text-body-secondary py-3">{{ __('Not linked to any learner yet.') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="card mt-4">
                <div class="card-header">{{ __('Active fee-liability rules') }}</div>
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead><tr><th>{{ __('Learner') }}</th><th>{{ __('Component') }}</th><th>{{ __('Share') }}</th><th></th></tr></thead>
                        <tbody>
                            @forelse ($liabilities as $liability)
                                <tr>
                                    <td>{{ $liability->student?->fullName() }}</td>
                                    <td>{{ $liability->component?->name ?? __('All other components') }}</td>
                                    <td>
                                        @if ($liability->share_type === 'percentage')
                                            {{ $liability->share_percent }}%
                                        @elseif ($liability->share_type === 'fixed')
                                            {{ $liability->currency }} {{ number_format($liability->share_amount_minor / 100, 2) }}
                                        @else
                                            {{ __('Full component') }}
                                        @endif
                                    </td>
                                    <td class="text-end">
                                        @if ($liability->student)
                                            <a href="{{ route('finance.liabilities.editor', [$school, $liability->student]) }}" class="btn btn-sm btn-outline-secondary" wire:navigate>{{ __('Manage') }}</a>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="text-center text-body-secondary py-3">{{ __('No active liability rules.') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
