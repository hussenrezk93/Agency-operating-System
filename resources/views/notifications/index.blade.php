@extends('layouts.app')
@section('title', __('agencyos.notifications.index.title'))
@section('page', 'notifications')
@section('content')
<main class="page">
    <div class="page-head">
        <div>
            <h1>{{ __('agencyos.notifications.index.title') }}</h1>
            <div class="page-sub">{{ __('agencyos.notifications.index.subtitle') }}</div>
        </div>
    </div>

    @if(session('status'))
        <div class="alert alert-success" style="margin-bottom:16px"><div>{{ session('status') }}</div></div>
    @endif

    <div class="card">
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
                                <span class="badge b-changes" style="margin-right:6px"><span class="bdot"></span>{{ __('agencyos.notifications.index.unread') }}</span>
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
