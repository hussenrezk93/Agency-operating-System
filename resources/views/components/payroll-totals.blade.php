@props(['totals', 'monthStart'])
{{-- The month's bottom line. It sits on BOTH payroll screens (product decision 2026-09:
     salaries and spending are separate pages) because the grand total is the number the
     Manager is after, and it must not depend on which of the two he happens to be on. --}}
@php
    $cur = __('agencyos.payroll.currency');
    $money = fn ($amount) => number_format((float) $amount, 2).' '.$cur;
@endphp
<div class="pay-totals">
    <article class="pay-total">
        <span class="pay-total-label">{{ __('agencyos.payroll.total_payroll') }}</span>
        <span class="pay-total-value">{{ $money($totals['payroll']) }}</span>
        <span class="pay-total-sub">{{ __('agencyos.payroll.people_count', ['closed' => $totals['closed'], 'people' => $totals['people']]) }}</span>
    </article>
    <article class="pay-total">
        <span class="pay-total-label">{{ __('agencyos.payroll.total_expenses') }}</span>
        <span class="pay-total-value">{{ $money($totals['expenses']) }}</span>
        <span class="pay-total-sub">{{ \Illuminate\Support\Carbon::parse($monthStart)->translatedFormat('F Y') }}</span>
    </article>
    <article class="pay-total is-grand">
        <span class="pay-total-label">{{ __('agencyos.payroll.grand_total') }}</span>
        <span class="pay-total-value">{{ $money($totals['total']) }}</span>
        <span class="pay-total-sub">{{ __('agencyos.payroll.grand_total_hint') }}</span>
    </article>
</div>
