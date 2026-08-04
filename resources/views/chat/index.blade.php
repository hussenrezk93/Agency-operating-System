@extends('layouts.app')
@section('title', __('agencyos.chat.index.title'))
@section('page', 'chat')
@section('content')
@php
    $actorId = auth()->id();

    $initialsOf = function (?string $name): string {
        return collect(preg_split('/\s+/u', trim($name ?? '')))
            ->filter()
            ->take(2)
            ->map(fn ($p) => mb_strtoupper(mb_substr($p, 0, 1)))
            ->implode('');
    };

    $typeIcon = [
        'employee_tl' => '🧑‍💼',
        'department_group' => '👥',
        'direct_tl' => '🤝',
        'all_tls' => '⭐',
        'manager_tls' => '🧭',
        'direct' => '💬',
    ];

    $titleFor = function ($conv) use ($actorId) {
        if ($conv->title) {
            return $conv->title;
        }
        if (in_array($conv->type->value, ['employee_tl', 'direct_tl', 'direct'], true)) {
            $other = $conv->members->firstWhere('user_id', '!=', $actorId);
            if ($other?->user) {
                return $other->user->full_name;
            }
        }

        return __('agencyos.chat.type.'.$conv->type->value);
    };

    $previewFor = function ($conv) {
        $msg = $conv->latestMessage;
        if (! $msg) {
            return null;
        }

        return $msg->isDeleted() ? __('agencyos.chat.index.deleted_placeholder') : ($msg->link_url ? '🔗 '.$msg->link_url : $msg->body);
    };
@endphp
<main class="page chatpage @if($conversation) chatpage-thread @endif" style="padding-bottom:0">
    <div class="page-head">
        <div>
            <h1>{{ __('agencyos.chat.index.title') }}</h1>
            <div class="page-sub">{{ __('agencyos.chat.index.subtitle') }}</div>
        </div>
    </div>

    @if(session('status'))
        <div class="alert alert-success" style="margin-bottom:16px"><div>{{ session('status') }}</div></div>
    @endif
    @if($errors->any())
        <div class="alert alert-danger" style="margin-bottom:16px"><div>{{ $errors->first() }}</div></div>
    @endif

    <div class="chat-layout">
        <div class="chat-list" id="chatList">
            <div style="padding:10px;border-bottom:1px solid var(--color-border)">
                <input type="search" id="chatSearch" class="select" style="width:100%" placeholder="{{ __('agencyos.chat.index.search_placeholder') }}" aria-label="{{ __('agencyos.chat.index.search_placeholder') }}">
            </div>
            <div style="overflow-y:auto">
                @forelse($conversations as $conv)
                    @php($isOpen = $conversation && $conversation->id === $conv->id)
                    <a href="{{ route('chat.show', $conv) }}" class="conv @if($isOpen) on @endif" data-q="{{ mb_strtolower($titleFor($conv)) }}">
                        <span class="avatar sm">{{ $conv->title || in_array($conv->type->value, ['department_group','all_tls','manager_tls'], true) ? $typeIcon[$conv->type->value] : $initialsOf($titleFor($conv)) }}</span>
                        <div style="flex:1;min-width:0">
                            <div class="cname">{{ $titleFor($conv) }}</div>
                            <div class="clast">{{ $previewFor($conv) ?? __('agencyos.chat.type.'.$conv->type->value) }}</div>
                        </div>
                        @if($conv->latestMessage)
                            <span class="ctime">{{ $conv->latestMessage->created_at->format('H:i') }}</span>
                        @endif
                    </a>
                @empty
                    <div class="empty">{{ __('agencyos.chat.index.no_conversations') }}</div>
                @endforelse

                @if($canStartDirect)
                    <details class="dir-row">
                        <summary>{{ __('agencyos.chat.index.start_direct') }}</summary>
                        <div class="dir-row-body">
                            <form method="POST" action="{{ route('chat.direct') }}">
                                @csrf
                                <div class="field @error('user_id') bad @enderror">
                                    <select name="user_id" required>
                                        <option value="">{{ __('agencyos.chat.index.pick_leader') }}</option>
                                        @foreach($directCandidates as $leader)
                                            <option value="{{ $leader->id }}">{{ $leader->full_name }}</option>
                                        @endforeach
                                    </select>
                                    @error('user_id')<div class="err">{{ $message }}</div>@enderror
                                </div>
                                <button type="submit" class="btn btn-outline btn-sm">{{ __('agencyos.chat.index.start_button') }}</button>
                            </form>
                        </div>
                    </details>
                @endif

                @php($directoryEmpty = $directory['departments']->isEmpty() && $directory['managers']->isEmpty() && $directory['admins']->isEmpty())
                @if(! $directoryEmpty)
                    <div class="small muted" style="padding:12px 16px 4px;font-weight:700;text-transform:uppercase;letter-spacing:.5px">{{ __('agencyos.chat.directory.title') }}</div>

                    @foreach($directory['departments'] as $group)
                        <details class="dir-row" data-q="{{ mb_strtolower($group['department']->name) }}">
                            <summary>{{ $group['department']->name }}</summary>
                            <div class="dir-row-body">
                                @foreach($group['members'] as $person)
                                    <div class="kv-row">
                                        <span class="inline"><span class="avatar sm grey">{{ $initialsOf($person->full_name) }}</span> {{ $person->full_name }} <span class="small muted">({{ __('agencyos.roles.'.$person->roleCode()->value) }})</span></span>
                                        <form method="POST" action="{{ route('chat.direct-message') }}">
                                            @csrf
                                            <input type="hidden" name="user_id" value="{{ $person->id }}">
                                            <button type="submit" class="btn btn-outline btn-sm">{{ __('agencyos.chat.directory.message_button') }}</button>
                                        </form>
                                    </div>
                                @endforeach
                            </div>
                        </details>
                    @endforeach

                    @if($directory['managers']->isNotEmpty())
                        <details class="dir-row" data-q="{{ mb_strtolower(__('agencyos.chat.directory.managers')) }}">
                            <summary>{{ __('agencyos.chat.directory.managers') }}</summary>
                            <div class="dir-row-body">
                                @foreach($directory['managers'] as $person)
                                    <div class="kv-row">
                                        <span class="inline"><span class="avatar sm grey">{{ $initialsOf($person->full_name) }}</span> {{ $person->full_name }}</span>
                                        <form method="POST" action="{{ route('chat.direct-message') }}">
                                            @csrf
                                            <input type="hidden" name="user_id" value="{{ $person->id }}">
                                            <button type="submit" class="btn btn-outline btn-sm">{{ __('agencyos.chat.directory.message_button') }}</button>
                                        </form>
                                    </div>
                                @endforeach
                            </div>
                        </details>
                    @endif

                    @if($directory['admins']->isNotEmpty())
                        <details class="dir-row" data-q="{{ mb_strtolower(__('agencyos.chat.directory.admins')) }}">
                            <summary>{{ __('agencyos.chat.directory.admins') }}</summary>
                            <div class="dir-row-body">
                                @foreach($directory['admins'] as $person)
                                    <div class="kv-row">
                                        <span class="inline"><span class="avatar sm grey">{{ $initialsOf($person->full_name) }}</span> {{ $person->full_name }}</span>
                                        <form method="POST" action="{{ route('chat.direct-message') }}">
                                            @csrf
                                            <input type="hidden" name="user_id" value="{{ $person->id }}">
                                            <button type="submit" class="btn btn-outline btn-sm">{{ __('agencyos.chat.directory.message_button') }}</button>
                                        </form>
                                    </div>
                                @endforeach
                            </div>
                        </details>
                    @endif
                @endif
            </div>
        </div>

        <div class="chat-pane">
            @if($conversation)
                <div class="chat-head">
                    <a href="{{ route('chat.index') }}" class="btn btn-outline btn-sm only-mobile back-link">{{ __('agencyos.chat.index.back_to_conversations') }}</a>
                    <span class="avatar">{{ $conversation->title || in_array($conversation->type->value, ['department_group','all_tls','manager_tls'], true) ? $typeIcon[$conversation->type->value] : $initialsOf($titleFor($conversation)) }}</span>
                    <div>
                        <b>{{ $titleFor($conversation) }}</b>
                        <div class="small muted">{{ __('agencyos.chat.type.'.$conversation->type->value) }}</div>
                    </div>
                </div>

                <div class="chat-msgs" id="chatMsgs">
                    @forelse($messages as $msg)
                        @php($msgDate = $msg->created_at->format('Y-m-d'))
                        @if($msgDate !== ($lastDate ?? null))
                            <div class="chat-day">{{ $msg->created_at->translatedFormat('d M Y') }}</div>
                        @endif
                        @php($lastDate = $msgDate)

                        <div class="msg @if($msg->sender_id === $actorId) me @endif">
                            <span class="avatar sm">{{ $initialsOf($msg->sender->full_name) }}</span>
                            <div>
                                @if($msg->isDeleted())
                                    <div class="bubble deleted">{{ __('agencyos.chat.index.deleted_placeholder') }}</div>
                                @else
                                    <div class="bubble">
                                        @if($msg->link_url)
                                            <a href="{{ $msg->link_url }}" target="_blank" rel="noopener">🔗 {{ $msg->link_url }}</a>
                                        @else
                                            {{ $msg->body }}
                                        @endif
                                    </div>
                                @endif
                                <div class="mmeta">
                                    {{ $msg->sender->full_name }} · {{ $msg->created_at->format('H:i') }}
                                    @if(! $msg->isDeleted() && $msg->sender_id === $actorId)
                                        · <form method="POST" action="{{ route('chat.messages.destroy', $msg) }}" style="display:inline">
                                            @csrf
                                            <button type="submit" class="chat-msg del" style="opacity:1;border:0;background:transparent;padding:0">✕ {{ __('agencyos.chat.index.delete') }}</button>
                                        </form>
                                    @endif
                                </div>
                            </div>
                        </div>
                    @empty
                        <div class="empty" style="margin:auto">💬<br>{{ __('agencyos.chat.index.no_messages') }}</div>
                    @endforelse
                </div>

                <form method="POST" action="{{ route('chat.messages.store', $conversation) }}" class="chat-input">
                    @csrf
                    <div class="field @error('message') bad @enderror" style="flex:1;margin:0">
                        <input type="text" name="message" aria-label="{{ __('agencyos.chat.index.compose_label') }}" placeholder="{{ __('agencyos.chat.index.compose_hint') }}" value="{{ old('message') }}" required autocomplete="off">
                        @error('message')<div class="err">{{ $message }}</div>@enderror
                    </div>
                    <button type="submit" class="btn btn-primary">{{ __('agencyos.chat.index.send') }}</button>
                </form>
            @else
                <div class="empty" style="margin:auto">💬<br>{{ __('agencyos.chat.index.pick_a_conversation') }}</div>
            @endif
        </div>
    </div>
</main>
<script>
(function () {
    var q = document.getElementById('chatSearch');
    var list = document.getElementById('chatList');
    if (! q || ! list) return;
    q.addEventListener('input', function () {
        var term = q.value.trim().toLowerCase();
        list.querySelectorAll('[data-q]').forEach(function (row) {
            row.style.display = ! term || row.dataset.q.indexOf(term) > -1 ? '' : 'none';
        });
    });

    var msgs = document.getElementById('chatMsgs');
    if (msgs) msgs.scrollTop = msgs.scrollHeight;
})();
</script>
@endsection
