@extends('layouts.app')
@section('title', __('agencyos.notifications.index.title'))
@section('page', 'notifications')
@section('page_header')
    <div class="page-head">
        <div>
            <h1>{{ __('agencyos.notifications.index.title') }}</h1>
            <div class="page-sub">{{ __('agencyos.notifications.index.subtitle') }}</div>
        </div>
    </div>
    <x-topbar-controls/>
@endsection
@section('content')
<main class="page">
    @if(session('status'))
        <div class="alert alert-success" style="margin-bottom:16px"><div>{{ session('status') }}</div></div>
    @endif

    @if($notifications->contains(fn ($notification) => ! $notification->is_read))
        <div style="display:flex;justify-content:flex-end;margin-bottom:10px">
            <form method="POST" action="{{ route('notifications.read-all') }}">
                @csrf
                <button type="submit" class="btn btn-sm btn-outline">{{ __('agencyos.notifications.index.mark_all_read') }}</button>
            </form>
        </div>
    @endif

    <div class="card glass-dark">
        <div class="table-wrap">
            <table>
                <thead>
                <tr>
                    <th>{{ __('agencyos.notifications.index.column_title') }}</th>
                    <th>{{ __('agencyos.notifications.index.column_body') }}</th>
                    <th>{{ __('agencyos.notifications.index.column_date') }}</th>
                    <th></th>
                </tr>
                </thead>
                <tbody>
                @forelse($notifications as $notification)
                    <tr style="{{ $notification->is_read ? '' : 'font-weight:700' }}">
                        <td>
                            @unless($notification->is_read)
                                <span class="badge b-changes" style="margin-inline-end:6px"><span class="bdot"></span>{{ __('agencyos.notifications.index.unread') }}</span>
                            @endunless
                            {{ $notification->title }}
                        </td>
                        <td class="small">{{ $notification->body }}</td>
                        <td class="mono small">{{ $notification->created_at->format('Y-m-d H:i') }}</td>
                        <td style="text-align:end">
                            @unless($notification->is_read)
                                <form method="POST" action="{{ route('notifications.read', $notification) }}">
                                    @csrf
                                    <button type="submit" class="btn btn-sm btn-outline">{{ __('agencyos.notifications.index.mark_read') }}</button>
                                </form>
                            @endunless
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="4" style="text-align:center;padding:34px" class="muted">{{ __('agencyos.notifications.index.empty') }}</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</main>
@endsection
