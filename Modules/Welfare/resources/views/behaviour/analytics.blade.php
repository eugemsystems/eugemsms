<div>
    <h4 class="mb-1">{{ __('Behaviour analytics') }}</h4>
    <p class="text-body-secondary mb-4">{{ __('Aggregate counts by category, for pastoral intervention — never a per-learner ranking (BR-BRD-07-019).') }}</p>

    <div class="card">
        <div class="table-responsive">
            <table class="table table-sm mb-0">
                <thead><tr><th>{{ __('Category') }}</th><th>{{ __('Polarity') }}</th><th>{{ __('Total') }}</th></tr></thead>
                <tbody>
                    @forelse ($byCategory as $row)
                        <tr>
                            <td>{{ $row->category_name }}</td>
                            <td><span class="badge text-bg-{{ $row->polarity === 'positive' ? 'success' : 'secondary' }}">{{ $row->polarity }}</span></td>
                            <td>{{ $row->total }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="text-center text-body-secondary py-3">{{ __('No data.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
