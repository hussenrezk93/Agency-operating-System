@extends('layouts.app')
@section('title', __('agencyos.reports.title'))
@section('page', 'reports')
@section('page_header')
    <div class="page-head">
        <div>
            <h1>{{ __('agencyos.reports.title') }}</h1>
            <div class="page-sub">{{ \Illuminate\Support\Carbon::parse($monthStart)->translatedFormat('F Y') }}</div>
        </div>
    </div>
    <x-topbar-controls/>
@endsection
@section('content')
<main class="page">
    @php
        $currentMonthKey = \Illuminate\Support\Carbon::parse($monthStart)->format('Y-m');
        $monthOptions = collect($availableMonths)->isNotEmpty()
            ? collect($availableMonths)->mapWithKeys(fn ($month) => [
                \Illuminate\Support\Carbon::parse($month)->format('Y-m') => \Illuminate\Support\Carbon::parse($month)->translatedFormat('F Y'),
            ])
            : collect([$currentMonthKey => \Illuminate\Support\Carbon::parse($monthStart)->translatedFormat('F Y')]);
    @endphp

    @if(session('status'))
        <div class="alert alert-success" style="margin-bottom:16px"><div>{{ session('status') }}</div></div>
    @endif
    @if($errors->any())
        <div class="alert alert-danger" style="margin-bottom:16px"><div>{{ $errors->first() }}</div></div>
    @endif

    {{-- Print letterhead — screen-hidden, shown only on paper, same treatment the daily
         department report already uses so both reports leave the building looking alike. --}}
    <div class="print-only print-letterhead">
        <div class="print-letterhead-band">
            <img src="{{ asset('images/logo.svg') }}" alt="Agency OS">
        </div>
        <div class="print-letterhead-meta">
            <h1>{{ __('agencyos.reports.print_title') }}</h1>
            <div>{{ \Illuminate\Support\Carbon::parse($monthStart)->translatedFormat('F Y') }}</div>
            <div>{{ __('agencyos.reports.print_generated', ['date' => now()->translatedFormat('Y-m-d H:i')]) }}</div>
        </div>
    </div>

    <div class="dx-card print-hide" style="padding:12px 14px;display:flex;flex-direction:row;gap:10px;align-items:center;margin-bottom:14px">
        <x-filter-select name="month" :options="$monthOptions" :selected="$currentMonthKey" :placeholder="$monthOptions[$currentMonthKey] ?? $currentMonthKey" :allow-clear="false"/>
        <button type="button" class="btn btn-outline btn-sm" style="margin-inline-start:auto" onclick="window.print()">
            <x-icon name="printer"/> {{ __('agencyos.reports.print') }}
        </button>
    </div>

    <div class="dx-card">
        <div class="dx-card-head has-line"><div><h2>{{ __('agencyos.reports.employee_report') }}</h2></div></div>
        <div class="dx-table-wrap">
            <table class="dx-table">
                <thead>
                <tr>
                    <th>{{ __('agencyos.reports.employee') }}</th>
                    <th>{{ __('agencyos.reports.due_steps') }}</th>
                    <th>{{ __('agencyos.reports.on_time') }}</th>
                    <th>{{ __('agencyos.reports.late') }}</th>
                    <th>{{ __('agencyos.reports.score') }}</th>
                    <th>{{ __('agencyos.reports.bonus') }}</th>
                    <th>{{ __('agencyos.reports.deduction') }}</th>
                    @if($canAdjust)<th></th>@endif
                </tr>
                </thead>
                <tbody>
                @forelse($groups as $group)
                    <tr class="rep-group-row">
                        <td colspan="{{ $canAdjust ? 8 : 7 }}">{{ $group['department'] ?? __('agencyos.reports.no_department') }}</td>
                    </tr>
                    @foreach($group['rows'] as $snapshot)
                        @php
                            $rows = $adjustments[$snapshot->user_id] ?? collect();
                            $bonuses = $rows->where('type', \App\Enums\AdjustmentType::Bonus);
                            $deductions = $rows->where('type', \App\Enums\AdjustmentType::Deduction);
                            $isLeader = $snapshot->snapshot_type === \App\Enums\SnapshotType::TlPersonal;
                        @endphp
                        <tr>
                            <td>
                                @if($snapshot->user)
                                    <a class="dx-td-main" href="{{ route('performance.show', $snapshot->user) }}">{{ $snapshot->user->full_name }}</a>
                                    <span class="dx-td-sub">{{ $isLeader ? __('agencyos.roles.tl') : __('agencyos.roles.employee') }}</span>
                                @else
                                    —
                                @endif
                            </td>
                            <td class="dx-td-num">{{ $snapshot->due_steps }}</td>
                            <td class="dx-td-num">{{ $snapshot->on_time_steps }}</td>
                            <td class="dx-td-num">{{ $snapshot->overdue_steps }}</td>
                            <td class="dx-td-num">{{ $snapshot->displayScore() }}</td>
                            <td>
                                <x-adjustment-cell :entries="$bonuses" :can-adjust="$canAdjust"/>
                            </td>
                            <td>
                                <x-adjustment-cell :entries="$deductions" :can-adjust="$canAdjust"/>
                            </td>
                            @if($canAdjust)
                                <td class="dx-td-end">
                                    <button type="button" class="btn btn-outline btn-sm"
                                            onclick="document.getElementById('adj-form-{{ $snapshot->user_id }}').hidden = !document.getElementById('adj-form-{{ $snapshot->user_id }}').hidden">
                                        {{ __('agencyos.reports.add_adjustment') }}
                                    </button>
                                </td>
                            @endif
                        </tr>
                        @if($canAdjust && $snapshot->user)
                            <tr id="adj-form-{{ $snapshot->user_id }}" hidden>
                                <td colspan="8">
                                    <form method="POST" action="{{ route('reports.adjustments.store') }}" class="rep-adj-form">
                                        @csrf
                                        <input type="hidden" name="user_id" value="{{ $snapshot->user_id }}">
                                        <input type="hidden" name="month" value="{{ $currentMonthKey }}">
                                        <div class="field">
                                            <label class="req">{{ __('agencyos.reports.adjustment_type') }}</label>
                                            <x-form-select name="type" required
                                                :options="['bonus' => __('agencyos.reports.bonus'), 'deduction' => __('agencyos.reports.deduction')]"
                                                selected="bonus"/>
                                        </div>
                                        <div class="field">
                                            <label class="req">{{ __('agencyos.reports.amount') }}</label>
                                            <input type="number" name="amount" step="0.01" min="0.01" required>
                                        </div>
                                        <div class="field">
                                            <label class="req">{{ __('agencyos.reports.reason') }}</label>
                                            <input type="text" name="reason" maxlength="1000" required>
                                        </div>
                                        <div class="field">
                                            <button type="submit" class="btn btn-primary btn-sm">{{ __('agencyos.reports.save_adjustment') }}</button>
                                        </div>
                                    </form>
                                </td>
                            </tr>
                        @endif
                    @endforeach
                @empty
                    @if($admins->isEmpty())
                        <tr><td colspan="{{ $canAdjust ? 8 : 7 }}" class="dx-empty-cell">{{ __('agencyos.reports.no_data') }}</td></tr>
                    @endif
                @endforelse

                {{-- Admins are never scored (PerformanceService only snapshots Employees
                     and Team Leaders), so their row carries no figures — it exists purely
                     so a bonus or deduction has somewhere to live. --}}
                @if($admins->isNotEmpty())
                    <tr class="rep-group-row">
                        <td colspan="{{ $canAdjust ? 8 : 7 }}">{{ __('agencyos.reports.admins') }}</td>
                    </tr>
                    @foreach($admins as $admin)
                        @php
                            $rows = $adjustments[$admin->id] ?? collect();
                        @endphp
                        <tr>
                            <td>
                                <span class="dx-td-main">{{ $admin->full_name }}</span>
                                <span class="dx-td-sub">{{ __('agencyos.roles.admin') }}</span>
                            </td>
                            <td class="dx-td-num">—</td>
                            <td class="dx-td-num">—</td>
                            <td class="dx-td-num">—</td>
                            <td class="dx-td-num">—</td>
                            <td><x-adjustment-cell :entries="$rows->where('type', \App\Enums\AdjustmentType::Bonus)" :can-adjust="$canAdjust"/></td>
                            <td><x-adjustment-cell :entries="$rows->where('type', \App\Enums\AdjustmentType::Deduction)" :can-adjust="$canAdjust"/></td>
                            @if($canAdjust)
                                <td class="dx-td-end">
                                    <button type="button" class="btn btn-outline btn-sm"
                                            onclick="document.getElementById('adj-form-{{ $admin->id }}').hidden = !document.getElementById('adj-form-{{ $admin->id }}').hidden">
                                        {{ __('agencyos.reports.add_adjustment') }}
                                    </button>
                                </td>
                            @endif
                        </tr>
                        @if($canAdjust)
                            <tr id="adj-form-{{ $admin->id }}" hidden>
                                <td colspan="8">
                                    <form method="POST" action="{{ route('reports.adjustments.store') }}" class="rep-adj-form">
                                        @csrf
                                        <input type="hidden" name="user_id" value="{{ $admin->id }}">
                                        <input type="hidden" name="month" value="{{ $currentMonthKey }}">
                                        <div class="field">
                                            <label class="req">{{ __('agencyos.reports.adjustment_type') }}</label>
                                            <x-form-select name="type" required
                                                :options="['bonus' => __('agencyos.reports.bonus'), 'deduction' => __('agencyos.reports.deduction')]"
                                                selected="bonus"/>
                                        </div>
                                        <div class="field">
                                            <label class="req">{{ __('agencyos.reports.amount') }}</label>
                                            <input type="number" name="amount" step="0.01" min="0.01" required>
                                        </div>
                                        <div class="field">
                                            <label class="req">{{ __('agencyos.reports.reason') }}</label>
                                            <input type="text" name="reason" maxlength="1000" required>
                                        </div>
                                        <div class="field">
                                            <button type="submit" class="btn btn-primary btn-sm">{{ __('agencyos.reports.save_adjustment') }}</button>
                                        </div>
                                    </form>
                                </td>
                            </tr>
                        @endif
                    @endforeach
                @endif
                </tbody>
            </table>
        </div>
    </div>
</main>
@endsection
