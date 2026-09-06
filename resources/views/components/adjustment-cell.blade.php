@props(['entries', 'canAdjust' => false, 'returnTo' => null])
{{-- One report cell's worth of bonuses (or deductions): the month's total, then each
     entry with the reason it was given for, so a number is never shown without its
     justification. The Manager gets a delete control per entry — there is no edit, a
     correction is a delete plus a fresh entry (see PerformanceAdjustmentPolicy).

     Every figure carries its own "bonus"/"deduction" word (asked for 2026-09): the
     column header names it once at the top of a long table, which is no help halfway
     down it, on a phone where the columns stack, or on a printout in one colour. --}}
@if($entries->isEmpty())
    <span class="muted small">—</span>
@else
    @php($type = $entries->first()->type)
    <span class="rep-adj-total">
        <span class="dx-td-num">{{ number_format((float) $entries->sum('amount'), 2) }}</span>
        <span class="badge {{ $type->badgeClass() }} rep-adj-kind">{{ __('agencyos.reports.'.$type->value) }}</span>
    </span>
    <span class="rep-adj-list">
        @foreach($entries as $entry)
            <span class="rep-adj-entry">
                <span class="rep-adj-amount">{{ number_format((float) $entry->amount, 2) }}</span>
                <span class="rep-adj-kind-inline">{{ __('agencyos.reports.'.$entry->type->value) }}</span>
                <span class="rep-adj-reason" title="{{ $entry->reason }}">{{ $entry->reason }}</span>
                @if($canAdjust)
                    <form method="POST" action="{{ route('reports.adjustments.destroy', $entry) }}"
                          onsubmit="return confirm('{{ __('agencyos.reports.remove_adjustment_confirm') }}')">
                        @csrf
                        @method('DELETE')
                        {{-- Which page asked for the delete, so the Manager comes back to
                             the screen they were working on instead of the reports page. --}}
                        @if($returnTo)<input type="hidden" name="return" value="{{ $returnTo }}">@endif
                        <button type="submit" class="rep-adj-remove" aria-label="{{ __('agencyos.reports.remove_adjustment') }}">&times;</button>
                    </form>
                @endif
            </span>
        @endforeach
    </span>
@endif
