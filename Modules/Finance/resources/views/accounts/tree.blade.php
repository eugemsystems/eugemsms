<div>
    <div class="d-flex align-items-center gap-2 mb-4">
        <div class="flex-grow-1">
            <h4 class="mb-1">{{ __('Chart of accounts') }}</h4>
            <p class="text-body-secondary mb-0">{{ __(':school\'s chart of accounts, with live balances from source.', ['school' => $school->name]) }}</p>
        </div>
        <a href="{{ route('finance.accounts.create', $school) }}" class="btn btn-primary" wire:navigate>
            <i class="ri ri-add-line me-1"></i>{{ __('New account') }}
        </a>
    </div>

    <div class="card">
        <div class="table-responsive">
            <table class="table mb-0 align-middle">
                <thead>
                    <tr>
                        <th>{{ __('Code') }}</th>
                        <th>{{ __('Name') }}</th>
                        <th>{{ __('Type') }}</th>
                        <th>{{ __('Postable') }}</th>
                        <th class="text-end">{{ __('Balance') }}</th>
                        <th class="text-end">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($roots as $root)
                        @include('finance::accounts.partials.tree-node', ['account' => $root, 'byParent' => $byParent, 'balances' => $balances, 'depth' => 0, 'school' => $school])
                    @empty
                        <tr>
                            <td colspan="6" class="text-center text-body-secondary py-4">{{ __('No accounts defined yet.') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
