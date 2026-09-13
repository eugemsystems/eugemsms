@php
    $childAccounts = $byParent->get($account->id, collect());
    $accountBalances = $balances[$account->id] ?? [];
@endphp
<tr wire:key="account-{{ $account->id }}">
    <td style="padding-left: {{ 1 + $depth * 1.5 }}rem">
        <a href="{{ route('finance.accounts.ledger', ['school' => $school, 'account' => $account]) }}" wire:navigate>{{ $account->code }}</a>
    </td>
    <td>
        {{ $account->name }}
        @if ($account->is_system)
            <span class="badge text-bg-secondary ms-1">{{ __('System') }}</span>
        @endif
        @if ($account->is_control_account)
            <span class="badge text-bg-info ms-1">{{ __('Control') }}</span>
        @endif
    </td>
    <td>{{ $account->accountType->name }}</td>
    <td>{{ $account->is_postable ? __('Yes') : __('No') }}</td>
    <td class="text-end">
        @forelse ($accountBalances as $currency => $minor)
            <div>{{ number_format($minor / 100, 2) }} {{ $currency }}</div>
        @empty
            <span class="text-body-secondary">—</span>
        @endforelse
    </td>
    <td class="text-end">
        <a href="{{ route('finance.accounts.edit', ['school' => $school, 'account' => $account]) }}" class="btn btn-icon btn-sm btn-outline-secondary" wire:navigate title="{{ __('Edit') }}" aria-label="{{ __('Edit') }}">
            <i class="icon-base ri ri-pencil-line icon-22px"></i>
        </a>
    </td>
</tr>
@foreach ($childAccounts as $child)
    @include('finance::accounts.partials.tree-node', ['account' => $child, 'byParent' => $byParent, 'balances' => $balances, 'depth' => $depth + 1, 'school' => $school])
@endforeach
