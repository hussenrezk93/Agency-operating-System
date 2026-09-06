@props(['badge'])

@php
    // Maps every existing .badge class (from TaskPresenter and friends) onto a
    // .dx-pill variant, so callers don't repeat this table at every call site.
    $variant = match ($badge['class']) {
        'b-progress', 'p-medium' => 'is-info',
        'b-review' => 'is-accent',
        'b-changes', 'p-high' => 'is-warning',
        'b-approved', 'b-done' => 'is-success',
        'b-overdue', 'p-urgent' => 'is-danger',
        default => 'is-muted',
    };
@endphp
<span {{ $attributes->merge(['class' => 'dx-pill '.$variant]) }}><i></i>{{ $badge['label'] }}</span>
