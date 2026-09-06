@props(['user', 'clickable' => false])
@php
    $name = $user?->full_name ?? $user?->username ?? 'U';
    $initials = collect(preg_split('/\s+/u', trim($name)))->filter()->take(2)->map(fn ($p) => mb_strtoupper(mb_substr($p, 0, 1)))->implode('');
    $url = $user?->avatar_url;
@endphp
@if($url)
    <img src="{{ $url }}" alt="{{ $name }}" class="avatar-img" @if($clickable) data-lightbox @endif>
@else
    {{ $initials ?: 'U' }}
@endif
