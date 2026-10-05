@if ($result)
    <div class="card mt-3">
        <div class="card-header d-flex justify-content-between">
            <span>{{ __('Result') }}</span>
            <span class="small text-body-secondary">{{ trans_choice(':count row|:count rows', $result['rowCount'], ['count' => $result['rowCount']]) }} · {{ $result['durationMs'] }} ms</span>
        </div>
        @if ($result['wasRedirected'])
            <div class="card-body"><div class="alert alert-warning small mb-0">{{ $result['redirectReason'] }}</div></div>
        @elseif ($result['rows'] === [])
            <div class="card-body text-body-secondary small">{{ __('No rows — either nothing matches, or none of the selected fields are ones you may read.') }}</div>
        @else
            <div class="table-responsive">
                <table class="table table-sm mb-0">
                    <thead><tr>@foreach (array_keys($result['rows'][0]) as $column) <th>{{ str_replace('_', ' ', $column) }}</th> @endforeach</tr></thead>
                    <tbody>
                        @foreach ($result['rows'] as $row)
                            <tr>@foreach ($row as $value) <td>{{ is_scalar($value) || $value === null ? $value : json_encode($value) }}</td> @endforeach</tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @if ($result['truncated']) <div class="card-footer small text-body-secondary">{{ __('Showing the first :n rows.', ['n' => count($result['rows'])]) }}</div> @endif
        @endif
    </div>
@endif
