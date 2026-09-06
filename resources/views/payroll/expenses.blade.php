@extends('layouts.app')
@section('title', __('agencyos.payroll.expenses_title'))
@section('page', 'expenses')
@section('page_header')
    <div class="page-head">
        <div>
            <h1>{{ __('agencyos.payroll.expenses_title') }}</h1>
            <div class="page-sub">{{ \Illuminate\Support\Carbon::parse($monthStart)->translatedFormat('F Y') }}</div>
        </div>
    </div>
    <x-topbar-controls/>
@endsection
@section('content')
@php
    $cur = __('agencyos.payroll.currency');
    $money = fn ($amount) => number_format((float) $amount, 2).' '.$cur;
    // What the "date" box on the add form starts at — the first of the month being
    // viewed, so an entry lands in the month the Manager is looking at by default.
    $defaultStart = \Illuminate\Support\Carbon::parse($monthStart)->startOfMonth()->toDateString();
@endphp
<main class="page">
    @if(session('status'))
        <div class="alert alert-success" style="margin-bottom:16px"><div>{{ session('status') }}</div></div>
    @endif
    @if($errors->any())
        <div class="alert alert-danger" style="margin-bottom:16px"><div>{{ $errors->first() }}</div></div>
    @endif

    {{-- Print letterhead — screen-hidden, shown only on paper, the same treatment the
         monthly report and the daily department report already use. --}}
    <div class="print-only print-letterhead">
        <div class="print-letterhead-band">
            <img src="{{ asset('images/logo.svg') }}" alt="Agency OS">
        </div>
        <div class="print-letterhead-meta">
            <h1>{{ __('agencyos.payroll.print_expenses_title') }}</h1>
            <div>{{ \Illuminate\Support\Carbon::parse($monthStart)->translatedFormat('F Y') }}</div>
            <div>{{ __('agencyos.payroll.print_generated', ['date' => now()->translatedFormat('Y-m-d H:i')]) }}</div>
        </div>
    </div>

    <div class="dx-card print-hide" style="padding:12px 14px;display:flex;flex-direction:row;gap:10px;align-items:center;margin-bottom:14px">
        <x-filter-select name="month" :options="$availableMonths" :selected="$monthKey"
                         :placeholder="$availableMonths[$monthKey] ?? $monthKey" :allow-clear="false"/>
        <button type="button" class="btn btn-outline btn-sm" style="margin-inline-start:auto" onclick="window.print()">
            <x-icon name="printer"/> {{ __('agencyos.payroll.print') }}
        </button>
    </div>

    <nav class="pay-tabs print-hide">
        <a href="{{ route('payroll.index', ['month' => $monthKey]) }}">{{ __('agencyos.payroll.tab_salaries') }}</a>
        <a class="is-active" href="{{ route('payroll.expenses.index', ['month' => $monthKey]) }}">{{ __('agencyos.payroll.tab_expenses') }}</a>
    </nav>

    <x-payroll-totals :totals="$totals" :month-start="$monthStart"/>

    <div class="dx-card">
        <div class="dx-card-head has-line"><div><h2>{{ __('agencyos.payroll.expenses') }}</h2></div></div>

        <form class="rep-adj-form print-hide" method="POST" action="{{ route('payroll.expenses.store') }}" style="padding:12px 16px">
            @csrf
            <div class="field">
                <label>{{ __('agencyos.payroll.expense_date') }}</label>
                <input type="date" name="spent_on" required value="{{ old('spent_on', $defaultStart) }}">
            </div>
            <div class="field" style="flex:1;min-width:220px">
                <label>{{ __('agencyos.payroll.expense_description') }}</label>
                <input type="text" name="description" maxlength="500" required value="{{ old('description') }}">
            </div>
            <div class="field">
                <label>{{ __('agencyos.payroll.expense_amount') }} ({{ $cur }})</label>
                <input type="number" step="0.01" min="0.01" name="amount" required value="{{ old('amount') }}">
            </div>
            <button type="submit" class="btn btn-primary btn-sm">{{ __('agencyos.payroll.add_expense') }}</button>
        </form>

        <div class="dx-table-wrap">
            <table class="dx-table">
                <thead>
                <tr>
                    <th>{{ __('agencyos.payroll.expense_date') }}</th>
                    <th>{{ __('agencyos.payroll.expense_description') }}</th>
                    <th>{{ __('agencyos.payroll.expense_by') }}</th>
                    <th>{{ __('agencyos.payroll.expense_amount') }}</th>
                    <th class="print-hide"></th>
                </tr>
                </thead>
                <tbody>
                @forelse($expenses as $expense)
                    <tr>
                        <td class="small">{{ $expense->spent_on->format('Y-m-d') }}</td>
                        <td>{{ $expense->description }}</td>
                        <td class="small muted">{{ $expense->createdBy?->full_name ?? '—' }}</td>
                        <td><span class="dx-td-num">{{ $money($expense->amount) }}</span></td>
                        <td class="dx-td-end print-hide">
                            <form method="POST" action="{{ route('payroll.expenses.destroy', $expense) }}"
                                  onsubmit="return confirm('{{ __('agencyos.payroll.remove_expense_confirm') }}')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="rep-adj-remove" aria-label="{{ __('agencyos.payroll.remove_expense') }}">&times;</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="dx-empty-cell">{{ __('agencyos.payroll.no_expenses') }}</td></tr>
                @endforelse
                </tbody>
                @if($expenses->isNotEmpty())
                    <tfoot>
                    <tr>
                        <td colspan="3" class="dx-td-end"><strong>{{ __('agencyos.payroll.total_expenses') }}</strong></td>
                        <td><span class="dx-td-num pay-net">{{ $money($totals['expenses']) }}</span></td>
                        <td class="print-hide"></td>
                    </tr>
                    </tfoot>
                @endif
            </table>
        </div>
    </div>
</main>
@endsection
