@extends('layouts.app')
@section('title', __('agencyos.payroll.title'))
@section('page', 'payroll')
@section('page_header')
    <div class="page-head">
        <div>
            <h1>{{ __('agencyos.payroll.title') }}</h1>
            <div class="page-sub">{{ \Illuminate\Support\Carbon::parse($monthStart)->translatedFormat('F Y') }}</div>
        </div>
    </div>
    <x-topbar-controls/>
@endsection
@section('content')
@php
    $cur = __('agencyos.payroll.currency');
    $money = fn ($amount) => number_format((float) $amount, 2).' '.$cur;
    // The dates a newly-opened window defaults to: the whole calendar month. The Manager
    // overwrites either one — the point of the feature is that cycles differ per person
    // — but starting from a sensible pair beats starting from two empty boxes.
    $defaultStart = \Illuminate\Support\Carbon::parse($monthStart)->startOfMonth()->toDateString();
    $defaultEnd = \Illuminate\Support\Carbon::parse($monthStart)->endOfMonth()->toDateString();
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
            <h1>{{ __('agencyos.payroll.print_title') }}</h1>
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

    {{-- Salaries and spending are two screens (product decision 2026-09); the month
         picker above applies to whichever one is open, so the pair is switched here. --}}
    <nav class="pay-tabs print-hide">
        <a class="is-active" href="{{ route('payroll.index', ['month' => $monthKey]) }}">{{ __('agencyos.payroll.tab_salaries') }}</a>
        <a href="{{ route('payroll.expenses.index', ['month' => $monthKey]) }}">{{ __('agencyos.payroll.tab_expenses') }}</a>
    </nav>

    <x-payroll-totals :totals="$totals" :month-start="$monthStart"/>

    <div class="dx-card" style="margin-bottom:18px">
        <div class="dx-card-head has-line">
            <div>
                <h2>{{ __('agencyos.payroll.people') }}</h2>
                <p class="small muted" style="margin:2px 0 0">{{ __('agencyos.payroll.adjustments_hint') }}</p>
            </div>
        </div>
        <div class="dx-table-wrap">
            <table class="dx-table">
                <thead>
                <tr>
                    <th>{{ __('agencyos.payroll.employee') }}</th>
                    <th>{{ __('agencyos.payroll.salary') }}</th>
                    <th>{{ __('agencyos.payroll.period') }}</th>
                    <th>{{ __('agencyos.payroll.bonus') }}</th>
                    <th>{{ __('agencyos.payroll.deduction') }}</th>
                    <th>{{ __('agencyos.payroll.net') }}</th>
                    <th>{{ __('agencyos.payroll.status') }}</th>
                    <th class="print-hide"></th>
                </tr>
                </thead>
                <tbody>
                @foreach($groups as $group)
                    <tr class="rep-group-row"><td colspan="8">{{ $group['department'] ?? __('agencyos.payroll.no_department') }}</td></tr>
                    @foreach($group['rows'] as $person)
                        @php
                            $salary = $salaries[$person->id] ?? null;
                            $period = $periods[$person->id] ?? null;
                            $settlement = $period !== null ? $settlements[$person->id] : null;
                            $rows = $adjustments[$person->id] ?? collect();
                        @endphp
                        <tr>
                            <td>
                                <div class="dx-cell">
                                    <div>
                                        <span class="dx-td-main">{{ $person->full_name }}</span>
                                        <span class="dx-td-sub">{{ __('agencyos.roles.'.$person->roleCode()->value) }}</span>
                                    </div>
                                </div>
                            </td>
                            <td>
                                @if($salary === null)
                                    <span class="muted small">{{ __('agencyos.payroll.no_salary') }}</span>
                                @else
                                    <span class="dx-td-num">{{ $money($salary->monthly_amount) }}</span>
                                @endif
                            </td>
                            <td>
                                @if($period === null)
                                    <span class="muted small">{{ __('agencyos.payroll.not_opened') }}</span>
                                @else
                                    <span class="small">{{ $period->period_start->format('Y-m-d') }} → {{ $period->period_end->format('Y-m-d') }}</span>
                                    @if($period->note)<span class="dx-td-sub">{{ $period->note }}</span>@endif
                                @endif
                            </td>
                            <td>
                                {{-- While the window is open these read live from the month's
                                     adjustments; once closed they are the frozen snapshot, which
                                     is why a closed row prints its own number instead. --}}
                                @if($period?->isClosed())
                                    <span class="rep-adj-total">
                                        <span class="dx-td-num">{{ $money($settlement['bonus']) }}</span>
                                        <span class="badge {{ \App\Enums\AdjustmentType::Bonus->badgeClass() }} rep-adj-kind">{{ __('agencyos.reports.bonus') }}</span>
                                    </span>
                                @else
                                    <x-adjustment-cell :entries="$rows->where('type', \App\Enums\AdjustmentType::Bonus)"
                                                       :can-adjust="true" return-to="payroll"/>
                                @endif
                            </td>
                            <td>
                                @if($period?->isClosed())
                                    <span class="rep-adj-total">
                                        <span class="dx-td-num">{{ $money($settlement['deduction']) }}</span>
                                        <span class="badge {{ \App\Enums\AdjustmentType::Deduction->badgeClass() }} rep-adj-kind">{{ __('agencyos.reports.deduction') }}</span>
                                    </span>
                                @else
                                    <x-adjustment-cell :entries="$rows->where('type', \App\Enums\AdjustmentType::Deduction)"
                                                       :can-adjust="true" return-to="payroll"/>
                                @endif
                            </td>
                            <td>
                                @if($settlement === null)
                                    <span class="muted small">—</span>
                                @else
                                    <span class="dx-td-num pay-net">{{ $money($settlement['net']) }}</span>
                                @endif
                            </td>
                            <td>
                                @if($period === null)
                                    <span class="muted small">—</span>
                                @else
                                    <span class="badge {{ $period->status->badgeClass() }}">
                                        {{ $period->isClosed() ? __('agencyos.payroll.status_closed') : __('agencyos.payroll.status_open') }}
                                    </span>
                                @endif
                            </td>
                            <td class="dx-td-end print-hide">
                                <div class="pay-actions">
                                    {{-- <details> keeps every form on the page without a line of
                                         JavaScript and without a second screen to navigate to. --}}
                                    <details class="pay-pop">
                                        <summary class="btn btn-outline btn-sm">
                                            {{ $salary === null ? __('agencyos.payroll.set_salary') : __('agencyos.payroll.edit_salary') }}
                                        </summary>
                                        <form class="pay-form" method="POST" action="{{ route('payroll.salaries.store') }}">
                                            @csrf
                                            <input type="hidden" name="user_id" value="{{ $person->id }}">
                                            <input type="hidden" name="month" value="{{ $monthKey }}">
                                            <div class="field">
                                                <label>{{ __('agencyos.payroll.amount') }} ({{ $cur }})</label>
                                                <input type="number" step="0.01" min="0" name="monthly_amount" required
                                                       value="{{ $salary?->monthly_amount }}">
                                            </div>
                                            <div class="field">
                                                <label>{{ __('agencyos.payroll.note_optional') }}</label>
                                                <input type="text" name="note" maxlength="500" value="{{ $salary?->note }}">
                                            </div>
                                            <button type="submit" class="btn btn-primary btn-sm">{{ __('agencyos.payroll.save') }}</button>
                                        </form>
                                    </details>

                                    {{-- The same bonus/deduction the reports page records —
                                         offered here too so the Manager can set the money and
                                         watch the take-home move without changing screens. --}}
                                    @if(! $period?->isClosed())
                                        <details class="pay-pop">
                                            <summary class="btn btn-outline btn-sm">{{ __('agencyos.reports.add_adjustment') }}</summary>
                                            <form class="pay-form" method="POST" action="{{ route('reports.adjustments.store') }}">
                                                @csrf
                                                <input type="hidden" name="user_id" value="{{ $person->id }}">
                                                <input type="hidden" name="month" value="{{ $monthKey }}">
                                                <input type="hidden" name="return" value="payroll">
                                                <div class="field">
                                                    <label>{{ __('agencyos.reports.adjustment_type') }}</label>
                                                    <select name="type" required>
                                                        <option value="bonus">{{ __('agencyos.reports.bonus') }}</option>
                                                        <option value="deduction">{{ __('agencyos.reports.deduction') }}</option>
                                                    </select>
                                                </div>
                                                <div class="field">
                                                    <label>{{ __('agencyos.payroll.amount') }} ({{ $cur }})</label>
                                                    <input type="number" step="0.01" min="0.01" name="amount" required>
                                                </div>
                                                <div class="field">
                                                    <label class="req">{{ __('agencyos.reports.reason') }}</label>
                                                    <input type="text" name="reason" maxlength="1000" required>
                                                </div>
                                                <button type="submit" class="btn btn-primary btn-sm">{{ __('agencyos.payroll.save') }}</button>
                                            </form>
                                        </details>
                                    @endif

                                    @if($period === null)
                                        <details class="pay-pop">
                                            <summary class="btn btn-outline btn-sm">{{ __('agencyos.payroll.open_period') }}</summary>
                                            <form class="pay-form" method="POST" action="{{ route('payroll.periods.open') }}">
                                                @csrf
                                                <input type="hidden" name="user_id" value="{{ $person->id }}">
                                                <input type="hidden" name="month" value="{{ $monthKey }}">
                                                <div class="field">
                                                    <label>{{ __('agencyos.payroll.from') }}</label>
                                                    <input type="date" name="period_start" required value="{{ $defaultStart }}">
                                                </div>
                                                <div class="field">
                                                    <label>{{ __('agencyos.payroll.to') }}</label>
                                                    <input type="date" name="period_end" required value="{{ $defaultEnd }}">
                                                </div>
                                                <div class="field">
                                                    <label>{{ __('agencyos.payroll.note_optional') }}</label>
                                                    <input type="text" name="note" maxlength="500">
                                                </div>
                                                <button type="submit" class="btn btn-primary btn-sm">{{ __('agencyos.payroll.open_period') }}</button>
                                            </form>
                                        </details>
                                    @elseif(! $period->isClosed())
                                        <details class="pay-pop">
                                            <summary class="btn btn-outline btn-sm">{{ __('agencyos.payroll.edit_period') }}</summary>
                                            <form class="pay-form" method="POST" action="{{ route('payroll.periods.update', $period) }}">
                                                @csrf
                                                @method('PATCH')
                                                <div class="field">
                                                    <label>{{ __('agencyos.payroll.from') }}</label>
                                                    <input type="date" name="period_start" required value="{{ $period->period_start->toDateString() }}">
                                                </div>
                                                <div class="field">
                                                    <label>{{ __('agencyos.payroll.to') }}</label>
                                                    <input type="date" name="period_end" required value="{{ $period->period_end->toDateString() }}">
                                                </div>
                                                <div class="field">
                                                    <label>{{ __('agencyos.payroll.salary') }} ({{ $cur }})</label>
                                                    <input type="number" step="0.01" min="0" name="base_amount" required value="{{ $period->base_amount }}">
                                                </div>
                                                <div class="field">
                                                    <label>{{ __('agencyos.payroll.note_optional') }}</label>
                                                    <input type="text" name="note" maxlength="500" value="{{ $period->note }}">
                                                </div>
                                                <button type="submit" class="btn btn-primary btn-sm">{{ __('agencyos.payroll.save') }}</button>
                                            </form>
                                        </details>
                                        <form method="POST" action="{{ route('payroll.periods.close', $period) }}"
                                              onsubmit="return confirm('{{ __('agencyos.payroll.close_confirm') }}')">
                                            @csrf
                                            <button type="submit" class="btn btn-primary btn-sm">{{ __('agencyos.payroll.close_period') }}</button>
                                        </form>
                                    @else
                                        <form method="POST" action="{{ route('payroll.periods.reopen', $period) }}"
                                              onsubmit="return confirm('{{ __('agencyos.payroll.reopen_confirm') }}')">
                                            @csrf
                                            <button type="submit" class="btn btn-outline btn-sm">{{ __('agencyos.payroll.reopen_period') }}</button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @endforeach
                @endforeach
                </tbody>
            </table>
        </div>
    </div>
</main>
@endsection
