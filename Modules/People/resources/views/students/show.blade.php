<div>
    <div class="d-flex align-items-center gap-2 mb-4">
        <div class="flex-grow-1">
            <h4 class="mb-1">{{ $student->fullName() }}</h4>
            <p class="text-body-secondary mb-0">
                {{ $student->admission_number }} ·
                <span class="badge {{ $student->status === 'active' ? 'text-bg-success' : 'text-bg-secondary' }}">{{ ucfirst($student->status) }}</span>
                · {{ $student->gradeLevel?->name }}@if ($student->schoolClass) — {{ $student->schoolClass->name }}@endif
            </p>
        </div>
        <a href="{{ route('academic.enrolment.subjects', [$school, $student]) }}" class="btn btn-outline-secondary" wire:navigate>{{ __('Subjects') }}</a>
        <div class="btn-group">
            <button type="button" class="btn btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown">{{ __('Record') }}</button>
            <ul class="dropdown-menu dropdown-menu-end">
                <li><a class="dropdown-item" href="{{ route('people.students.documents', [$school, $student]) }}" wire:navigate>{{ __('Documents') }}</a></li>
                <li><a class="dropdown-item" href="{{ route('people.students.prior-history', [$school, $student]) }}" wire:navigate>{{ __('Prior schooling') }}</a></li>
                <li><a class="dropdown-item" href="{{ route('people.students.siblings', [$school, $student]) }}" wire:navigate>{{ __('Siblings') }}</a></li>
                <li><a class="dropdown-item" href="{{ route('people.students.timeline', [$school, $student]) }}" wire:navigate>{{ __('Timeline') }}</a></li>
                <li><a class="dropdown-item" href="{{ route('people.students.id-card', [$school, $student]) }}" wire:navigate>{{ __('ID card') }}</a></li>
                <li><hr class="dropdown-divider"></li>
                <li><a class="dropdown-item text-danger" href="{{ route('people.students.transfer-out', [$school, $student]) }}" wire:navigate>{{ __('Transfer out') }}</a></li>
            </ul>
        </div>
        <a href="{{ route('people.students.change-status', [$school, $student]) }}" class="btn btn-outline-warning" wire:navigate>{{ __('Change status') }}</a>
        <a href="{{ route('people.students.change-attribute', [$school, $student]) }}" class="btn btn-outline-primary" wire:navigate>{{ __('Change billing attribute') }}</a>
        <a href="{{ route('people.students.edit', [$school, $student]) }}" class="btn btn-primary" wire:navigate>{{ __('Edit') }}</a>
    </div>

    <ul class="nav nav-tabs mb-4">
        @foreach (['overview' => __('Overview'), 'academic' => __('Academic'), 'financial' => __('Financial'), 'guardians' => __('Guardians')] as $tab => $label)
            <li class="nav-item">
                <button type="button" class="nav-link {{ $activeTab === $tab ? 'active' : '' }}" wire:click="setTab('{{ $tab }}')">{{ $label }}</button>
            </li>
        @endforeach
    </ul>

    @if ($activeTab === 'overview')
        <div class="row g-4">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">{{ __('Identity') }}</div>
                    <div class="card-body">
                        <dl class="row mb-0">
                            <dt class="col-5">{{ __('Full name') }}</dt><dd class="col-7">{{ $student->fullName() }}</dd>
                            <dt class="col-5">{{ __('Preferred name') }}</dt><dd class="col-7">{{ $student->preferred_name ?? '—' }}</dd>
                            <dt class="col-5">{{ __('Date of birth') }}</dt><dd class="col-7">{{ $student->date_of_birth->format('d M Y') }}</dd>
                            <dt class="col-5">{{ __('Gender') }}</dt><dd class="col-7">{{ ucfirst($student->gender) }}</dd>
                            <dt class="col-5">{{ __('Nationality') }}</dt><dd class="col-7">{{ $student->nationality }}</dd>
                            <dt class="col-5">{{ __('Entry cohort') }}</dt><dd class="col-7">{{ $student->entry_cohort_year }}</dd>
                        </dl>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">{{ __('Welfare flags') }}</div>
                    <div class="card-body">
                        <p class="text-body-secondary small">{{ __('Existence only — detail lives behind its own module and permissions (BR-PPL-01-022).') }}</p>
                        <div class="d-flex flex-wrap gap-2">
                            @if ($student->has_medical_alert) <span class="badge text-bg-danger">{{ __('Medical alert') }}</span> @endif
                            @if ($student->has_allergy_alert) <span class="badge text-bg-warning">{{ __('Allergy alert') }}</span> @endif
                            @if ($student->has_dietary_requirement) <span class="badge text-bg-info">{{ __('Dietary requirement') }}</span> @endif
                            @if ($student->has_sen_record) <span class="badge text-bg-info">{{ __('SEN record') }}</span> @endif
                            @if ($student->is_vulnerable) <span class="badge text-bg-warning">{{ __('Vulnerable') }}</span> @endif
                            @if (! $student->has_medical_alert && ! $student->has_allergy_alert && ! $student->has_dietary_requirement && ! $student->has_sen_record && ! $student->is_vulnerable)
                                <span class="text-body-secondary">{{ __('No flags recorded.') }}</span>
                            @endif
                        </div>
                    </div>
                </div>
                <div class="card mt-4">
                    <div class="card-header">{{ __('Address') }}</div>
                    <div class="card-body">
                        @if ($student->address_line_1 || $student->city)
                            {{ $student->address_line_1 }}<br>
                            @if ($student->address_line_2) {{ $student->address_line_2 }}<br> @endif
                            {{ $student->suburb }} {{ $student->city }} {{ $student->province }}
                        @else
                            <span class="text-body-secondary">{{ __('No address on file.') }}</span>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    @elseif ($activeTab === 'academic')
        <div class="row g-4">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">{{ __('Enrolment history') }}</div>
                    <div class="table-responsive">
                        <table class="table table-sm mb-0">
                            <thead><tr><th>{{ __('Term') }}</th><th>{{ __('Grade') }}</th><th>{{ __('Status') }}</th><th>{{ __('Started') }}</th></tr></thead>
                            <tbody>
                                @forelse ($enrolmentHistory as $enrolment)
                                    <tr>
                                        <td>{{ $enrolment->term_id }}</td>
                                        <td>{{ $enrolment->gradeLevel?->name }}</td>
                                        <td>{{ ucfirst($enrolment->status) }}</td>
                                        <td>{{ $enrolment->started_on->format('d M Y') }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="4" class="text-center text-body-secondary py-3">{{ __('No enrolment history.') }}</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">{{ __('Billing attribute history') }}</div>
                    <div class="table-responsive">
                        <table class="table table-sm mb-0">
                            <thead><tr><th>{{ __('Attribute') }}</th><th>{{ __('From → To') }}</th><th>{{ __('Effective') }}</th></tr></thead>
                            <tbody>
                                @forelse ($attributeHistory as $change)
                                    <tr>
                                        <td>{{ $change->attribute }}</td>
                                        <td>{{ $change->old_value ?? '—' }} → {{ $change->new_value }}</td>
                                        <td>{{ $change->effective_from->format('d M Y') }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="3" class="text-center text-body-secondary py-3">{{ __('No attribute changes recorded.') }}</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    @elseif ($activeTab === 'financial')
        <div class="card">
            <div class="card-header">{{ __('Outstanding balance') }}</div>
            <div class="card-body">
                @forelse ($balancesByCurrency as $row)
                    <h5>{{ $row->currency }} {{ number_format($row->total_minor / 100, 2) }}</h5>
                @empty
                    <p class="text-success mb-0">{{ __('No outstanding balance.') }}</p>
                @endforelse
            </div>
        </div>
    @elseif ($activeTab === 'guardians')
        <div class="card">
            <div class="card-header d-flex align-items-center justify-content-between">
                {{ __('Linked guardians') }}
                <a href="{{ route('people.students.guardians', [$school, $student]) }}" class="btn btn-sm btn-outline-primary" wire:navigate>{{ __('Manage guardians') }}</a>
            </div>
            <div class="table-responsive">
                <table class="table table-sm mb-0">
                    <thead><tr><th>{{ __('Name') }}</th><th>{{ __('Relationship') }}</th><th>{{ __('Roles') }}</th></tr></thead>
                    <tbody>
                        @forelse ($guardianLinks as $link)
                            <tr>
                                <td>{{ $link->guardian?->first_name }} {{ $link->guardian?->last_name }}</td>
                                <td>{{ ucfirst($link->relationship) }}</td>
                                <td>
                                    @if ($link->is_primary_contact) <span class="badge text-bg-primary">{{ __('Primary') }}</span> @endif
                                    @if ($link->is_fee_responsible) <span class="badge text-bg-success">{{ __('Fee responsible') }}</span> @endif
                                    @if ($link->is_emergency_contact) <span class="badge text-bg-warning">{{ __('Emergency') }}</span> @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="text-center text-body-secondary py-3">{{ __('No guardians linked yet.') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @endif
</div>
