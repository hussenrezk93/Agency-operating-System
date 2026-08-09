@extends('layouts.app')
@section('title', __('agencyos.chat.index.title'))
@section('page', 'chat')
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

    // For a group with no distinguishing title of its own (all_tls, manager_tls — and
    // department_group/employee_tl/direct_tl/direct once they somehow lack one too),
    // $titleFor() already falls back to the type label. Showing that same label again
    // underneath as a "subtitle" is a plain duplicate, not extra information.
    $typeLabelFor = fn ($conv) => __('agencyos.chat.type.'.$conv->type->value);
    $showsTypeAsSubtitle = fn ($conv) => $titleFor($conv) !== $typeLabelFor($conv);
@endphp
@section('page_header')
    <div class="page-head">
        <div>
            <h1>{{ __('agencyos.chat.index.title') }}</h1>
            <div class="page-sub">{{ __('agencyos.chat.index.subtitle') }}</div>
        </div>
    </div>
    <x-topbar-controls/>
@endsection
@section('content')
<main class="page chatpage @if($conversation) chatpage-thread @endif" style="padding-bottom:0">
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
                            @if($previewFor($conv) || $showsTypeAsSubtitle($conv))
                                <div class="clast">{{ $previewFor($conv) ?? $typeLabelFor($conv) }}</div>
                            @endif
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
                                    <x-form-select name="user_id" required
                                        :placeholder="__('agencyos.chat.index.pick_leader')" :options="$directCandidates->pluck('full_name', 'id')"/>
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
                        @if($showsTypeAsSubtitle($conversation))
                            <div class="small muted">{{ $typeLabelFor($conversation) }}</div>
                        @endif
                    </div>
                </div>

                <div class="chat-msgs" id="chatMsgs"
                     data-actor-id="{{ $actorId }}"
                     data-last-id="{{ $messages->last()->id ?? 0 }}"
                     data-last-date-key="{{ $messages->last()?->created_at->format('Y-m-d') ?? '' }}"
                     data-poll-url="{{ route('chat.poll', $conversation) }}"
                     data-channel="chat.conversation.{{ $conversation->id }}"
                     data-pusher-key="{{ config('broadcasting.connections.pusher.key') }}"
                     data-pusher-cluster="{{ config('broadcasting.connections.pusher.options.cluster') }}"
                     data-deleted-placeholder="{{ __('agencyos.chat.index.deleted_placeholder') }}"
                     data-delete-label="{{ __('agencyos.chat.index.delete') }}">
                    @forelse($messages as $msg)
                        @php($msgDate = $msg->created_at->format('Y-m-d'))
                        @if($msgDate !== ($lastDate ?? null))
                            <div class="chat-day">{{ $msg->created_at->translatedFormat('d M Y') }}</div>
                        @endif
                        @php($lastDate = $msgDate)

                        <div class="msg @if($msg->sender_id === $actorId) me @endif" data-message-id="{{ $msg->id }}">
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

                <form method="POST" action="{{ route('chat.messages.store', $conversation) }}" class="chat-input" id="chatComposeForm">
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
    if (q && list) {
        q.addEventListener('input', function () {
            var term = q.value.trim().toLowerCase();
            list.querySelectorAll('[data-q]').forEach(function (row) {
                row.style.display = ! term || row.dataset.q.indexOf(term) > -1 ? '' : 'none';
            });
        });
    }

    var msgs = document.getElementById('chatMsgs');
    if (! msgs) return;

    msgs.scrollTop = msgs.scrollHeight;

    // ---- real-time chat over Pusher: push new messages/deletions, no reload ----
    var actorId = parseInt(msgs.dataset.actorId, 10);
    var lastId = parseInt(msgs.dataset.lastId, 10) || 0;
    var lastDateKey = msgs.dataset.lastDateKey || '';
    var pollUrl = msgs.dataset.pollUrl;
    var csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

    // Seeded from the server-rendered messages already on the page, so a duplicate
    // arriving from more than one source (e.g. the backfill poll re-covering a message
    // a broadcast already delivered) is silently ignored instead of shown twice.
    var renderedIds = {};
    msgs.querySelectorAll('[data-message-id]').forEach(function (el) {
        renderedIds[el.dataset.messageId] = true;
    });

    function isNearBottom() {
        return msgs.scrollHeight - msgs.scrollTop - msgs.clientHeight < 80;
    }

    function appendMessage(payload) {
        if (renderedIds[payload.id]) return;
        renderedIds[payload.id] = true;

        var isMine = payload.sender_id === actorId;
        var stickToBottom = isMine || isNearBottom();

        if (payload.date_key && payload.date_key !== lastDateKey) {
            var day = document.createElement('div');
            day.className = 'chat-day';
            day.textContent = payload.date_label;
            msgs.appendChild(day);
            lastDateKey = payload.date_key;
        }

        var row = document.createElement('div');
        row.className = 'msg' + (isMine ? ' me' : '');
        row.dataset.messageId = payload.id;

        var avatar = document.createElement('span');
        avatar.className = 'avatar sm';
        avatar.textContent = payload.sender_initials;
        row.appendChild(avatar);

        var col = document.createElement('div');

        var bubble = document.createElement('div');
        if (payload.is_deleted) {
            bubble.className = 'bubble deleted';
            bubble.textContent = msgs.dataset.deletedPlaceholder;
        } else {
            bubble.className = 'bubble';
            if (payload.link_url) {
                var a = document.createElement('a');
                a.href = payload.link_url;
                a.target = '_blank';
                a.rel = 'noopener';
                a.textContent = '🔗 ' + payload.link_url;
                bubble.appendChild(a);
            } else {
                bubble.textContent = payload.body;
            }
        }
        col.appendChild(bubble);

        var meta = document.createElement('div');
        meta.className = 'mmeta';
        meta.textContent = payload.sender_name + ' · ' + payload.time;
        if (isMine && ! payload.is_deleted && payload.delete_url) {
            meta.appendChild(document.createTextNode(' · '));
            var delForm = document.createElement('form');
            delForm.method = 'POST';
            delForm.action = payload.delete_url;
            delForm.style.display = 'inline';
            var tokenInput = document.createElement('input');
            tokenInput.type = 'hidden';
            tokenInput.name = '_token';
            tokenInput.value = csrfToken;
            delForm.appendChild(tokenInput);
            var delBtn = document.createElement('button');
            delBtn.type = 'submit';
            delBtn.className = 'chat-msg del';
            delBtn.style.cssText = 'opacity:1;border:0;background:transparent;padding:0';
            delBtn.textContent = '✕ ' + msgs.dataset.deleteLabel;
            delForm.appendChild(delBtn);
            meta.appendChild(delForm);
        }
        col.appendChild(meta);

        row.appendChild(col);
        msgs.appendChild(row);

        if (stickToBottom) msgs.scrollTop = msgs.scrollHeight;
        if (payload.id > lastId) lastId = payload.id;
    }

    function markDeleted(id) {
        var row = msgs.querySelector('[data-message-id="' + id + '"]');
        if (! row) return;

        var bubble = row.querySelector('.bubble');
        if (bubble) {
            bubble.className = 'bubble deleted';
            bubble.textContent = msgs.dataset.deletedPlaceholder;
        }

        var delForm = row.querySelector('.mmeta form');
        if (delForm) delForm.remove();
    }

    // Backfill: a plain "everything after id X" fetch, used only (a) right after the
    // live channel subscription is confirmed — covering both the very first connect and
    // any later reconnect — and (b) as the fallback if Pusher never loads/connects at
    // all (offline, blocked script, bad key). Never runs on a timer.
    function poll() {
        fetch(pollUrl + '?after=' + lastId, { headers: { 'Accept': 'application/json' } })
            .then(function (res) { return res.ok ? res.json() : null; })
            .then(function (json) {
                if (! json) return;
                json.data.forEach(function (payload) {
                    if (payload.is_deleted && renderedIds[payload.id]) {
                        markDeleted(payload.id);

                        return;
                    }

                    appendMessage(payload);
                });
            })
            .catch(function () { /* offline — nothing more to do here */ });
    }

    if (msgs.dataset.pusherKey) {
        var pusherScript = document.createElement('script');
        pusherScript.src = 'https://js.pusher.com/8.4/pusher.min.js';
        pusherScript.onload = function () {
            var pusher = new Pusher(msgs.dataset.pusherKey, {
                cluster: msgs.dataset.pusherCluster,
                authEndpoint: '{{ url('/broadcasting/auth') }}',
                auth: { headers: { 'X-CSRF-TOKEN': csrfToken } },
            });

            var channel = pusher.subscribe('private-' + msgs.dataset.channel);
            channel.bind('message.new', appendMessage);
            channel.bind('message.deleted', function (payload) { markDeleted(payload.id); });
            channel.bind('pusher:subscription_succeeded', poll);
        };
        pusherScript.onerror = poll;
        document.head.appendChild(pusherScript);
    } else {
        // No Pusher key configured yet — still sync once so the page isn't stuck at
        // whatever it looked like at the moment it was loaded.
        poll();
    }

    var composeForm = document.getElementById('chatComposeForm');
    if (composeForm) {
        composeForm.addEventListener('submit', function (event) {
            var input = composeForm.querySelector('input[name="message"]');
            if (! input || ! input.value.trim()) return;

            event.preventDefault();
            var formData = new FormData(composeForm);

            fetch(composeForm.action, {
                method: 'POST',
                headers: { 'Accept': 'application/json' },
                body: formData,
            })
                .then(function (res) {
                    if (! res.ok) throw new Error('send failed');

                    return res.json();
                })
                .then(function (json) {
                    appendMessage(json.data);
                    input.value = '';
                    input.focus();
                })
                .catch(function () {
                    // JS/fetch failed for some reason — fall back to a normal form
                    // submit (classic POST + redirect) rather than silently dropping
                    // the message. HTMLFormElement.prototype.submit() bypasses this
                    // same 'submit' listener, so it cannot loop.
                    HTMLFormElement.prototype.submit.call(composeForm);
                });
        });
    }
})();
</script>
@endsection
