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

    <div class="dx-card">
        <div class="dx-table-wrap">
            <table class="dx-table">
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
                                <x-dx-pill :badge="['class' => 'b-changes', 'label' => __('agencyos.notifications.index.unread')]" style="margin-inline-end:6px"/>
                            @endunless
                            @if($notification->targetUrl($reportDates))
                                <form method="POST" action="{{ route('notifications.read', $notification) }}" style="display:inline">
                                    @csrf
                                    <button type="submit" style="background:none;border:0;padding:0;margin:0;font:inherit;font-weight:inherit;color:inherit;cursor:pointer;text-decoration:underline;text-align:start">{{ $notification->title }}</button>
                                </form>
                            @else
                                {{ $notification->title }}
                            @endif
                        </td>
                        <td>{{ $notification->body }}</td>
                        <td class="dx-td-num">{{ $notification->created_at->format('Y-m-d H:i') }}</td>
                        <td class="dx-td-end">
                            @unless($notification->is_read)
                                <form method="POST" action="{{ route('notifications.read', $notification) }}">
                                    @csrf
                                    <button type="submit" class="btn btn-sm btn-outline">{{ __('agencyos.notifications.index.mark_read') }}</button>
                                </form>
                            @endunless
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="dx-empty-cell">{{ __('agencyos.notifications.index.empty') }}</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        @if($notifications->hasPages())
            <div class="card-foot">{{ $notifications->links() }}</div>
        @endif
    </div>
</main>
@endsection
