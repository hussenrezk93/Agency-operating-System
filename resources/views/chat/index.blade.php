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

    // Only a genuinely one-on-one conversation has a single real person to show a photo
    // for — group types (department_group/all_tls/manager_tls) keep the type emoji/
    // initials regardless of $conv->title, since there's no single counterpart to show.
    $avatarUserFor = function ($conv) use ($actorId) {
        if (! in_array($conv->type->value, ['employee_tl', 'direct_tl', 'direct'], true)) {
            return null;
        }

        return $conv->members->firstWhere('user_id', '!=', $actorId)?->user;
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
            <div class="chat-search-wrap">
                <x-icon name="search" class="ic"/>
                <input type="search" id="chatSearch" class="chat-search" placeholder="{{ __('agencyos.chat.index.search_placeholder') }}" aria-label="{{ __('agencyos.chat.index.search_placeholder') }}">
            </div>
            <div style="flex:1;min-height:0;overflow-y:auto">
                @forelse($conversations as $conv)
                    @php($isOpen = $conversation && $conversation->id === $conv->id)
                    @php($unread = $unreadCounts[$conv->id] ?? 0)
                    <a href="{{ route('chat.show', $conv) }}" class="conv @if($isOpen) on @endif @if($unread > 0) unread @endif" data-q="{{ mb_strtolower($titleFor($conv)) }}">
                        <span class="avatar sm">
                            @if($avatarUserFor($conv))
                                <x-avatar :user="$avatarUserFor($conv)" :clickable="true"/>
                            @else
                                {{ $conv->title || in_array($conv->type->value, ['department_group','all_tls','manager_tls'], true) ? $typeIcon[$conv->type->value] : $initialsOf($titleFor($conv)) }}
                            @endif
                        </span>
                        <div style="flex:1;min-width:0">
                            <div class="cname">{{ $titleFor($conv) }}</div>
                            @if($previewFor($conv) || $showsTypeAsSubtitle($conv))
                                <div class="clast">{{ $previewFor($conv) ?? $typeLabelFor($conv) }}</div>
                            @endif
                        </div>
                        <div class="conv-meta">
                            @if($conv->latestMessage)
                                <span class="ctime">{{ $conv->latestMessage->created_at->format('H:i') }}</span>
                            @endif
                            @if($unread > 0)
                                <span class="conv-badge">{{ $unread > 99 ? '99+' : $unread }}</span>
                            @endif
                        </div>
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
                                    <div class="dx-kv">
                                        <span class="inline"><span class="avatar sm grey"><x-avatar :user="$person" :clickable="true"/></span> {{ $person->full_name }} <span class="small muted">({{ __('agencyos.roles.'.$person->roleCode()->value) }})</span></span>
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
                                    <div class="dx-kv">
                                        <span class="inline"><span class="avatar sm grey"><x-avatar :user="$person" :clickable="true"/></span> {{ $person->full_name }}</span>
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
                                    <div class="dx-kv">
                                        <span class="inline"><span class="avatar sm grey"><x-avatar :user="$person" :clickable="true"/></span> {{ $person->full_name }}</span>
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
                    <span class="avatar">
                        @if($avatarUserFor($conversation))
                            <x-avatar :user="$avatarUserFor($conversation)" :clickable="true"/>
                        @else
                            {{ $conversation->title || in_array($conversation->type->value, ['department_group','all_tls','manager_tls'], true) ? $typeIcon[$conversation->type->value] : $initialsOf($titleFor($conversation)) }}
                        @endif
                    </span>
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
                     data-delete-label="{{ __('agencyos.chat.index.delete') }}"
                     data-mark-read-url="{{ route('chat.read', $conversation) }}"
                     data-read-state="{{ $readState->map(fn ($s) => ['user_id' => $s['user_id'], 'last_read_message_id' => $s['last_read_message_id'], 'last_read_at' => $s['last_read_at']?->toIso8601String()])->toJson() }}">
                    @forelse($messages as $msg)
                        @php($msgDate = $msg->created_at->format('Y-m-d'))
                        @if($msgDate !== ($lastDate ?? null))
                            <div class="chat-day">{{ $msg->created_at->translatedFormat('d M Y') }}</div>
                        @endif
                        @php($lastDate = $msgDate)

                        <div class="msg @if($msg->sender_id === $actorId) me @endif" data-message-id="{{ $msg->id }}">
                            <span class="avatar sm"><x-avatar :user="$msg->sender" :clickable="true"/></span>
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
                                        <span class="msg-ticks"></span>
                                        <form method="POST" action="{{ route('chat.messages.destroy', $msg) }}" class="msg-del-form">
                                            @csrf
                                            <button type="submit" class="msg-del" aria-label="{{ __('agencyos.chat.index.delete') }}" title="{{ __('agencyos.chat.index.delete') }}"><x-icon name="trash" class="ic"/></button>
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
        if (payload.sender_avatar_url) {
            var avatarImg = document.createElement('img');
            avatarImg.className = 'avatar-img';
            avatarImg.src = payload.sender_avatar_url;
            avatarImg.alt = payload.sender_name;
            avatarImg.setAttribute('data-lightbox', '');
            avatar.appendChild(avatarImg);
        } else {
            avatar.textContent = payload.sender_initials;
        }
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
        if (isMine && ! payload.is_deleted) {
            meta.appendChild(document.createTextNode(' '));
            var ticks = document.createElement('span');
            ticks.className = 'msg-ticks';
            meta.appendChild(ticks);
        }
        if (isMine && ! payload.is_deleted && payload.delete_url) {
            var delForm = document.createElement('form');
            delForm.method = 'POST';
            delForm.action = payload.delete_url;
            delForm.className = 'msg-del-form';
            var tokenInput = document.createElement('input');
            tokenInput.type = 'hidden';
            tokenInput.name = '_token';
            tokenInput.value = csrfToken;
            delForm.appendChild(tokenInput);
            var delBtn = document.createElement('button');
            delBtn.type = 'submit';
            delBtn.className = 'msg-del';
            delBtn.setAttribute('aria-label', msgs.dataset.deleteLabel);
            delBtn.title = msgs.dataset.deleteLabel;
            delBtn.innerHTML = '<svg class="ic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" '
                + 'stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 7h16"/>'
                + '<path d="M9 7V4.5A1.5 1.5 0 0 1 10.5 3h3A1.5 1.5 0 0 1 15 4.5V7"/>'
                + '<path d="M6.5 7 7.3 19a2 2 0 0 0 2 1.9h5.4a2 2 0 0 0 2-1.9L17.5 7"/>'
                + '<path d="M10 11v6"/><path d="M14 11v6"/></svg>';
            delForm.appendChild(delBtn);
            meta.appendChild(delForm);
        }
        col.appendChild(meta);

        row.appendChild(col);
        msgs.appendChild(row);

        if (stickToBottom) msgs.scrollTop = msgs.scrollHeight;
        if (payload.id > lastId) lastId = payload.id;
        if (isMine) renderTicks(row);
    }

    // ---- read receipts: "seen" double-tick + time, WhatsApp-style ----
    // readState tracks every OTHER active member's read watermark; a message I sent is
    // "seen" once every one of them has read up to (or past) it. Seeded from the page's
    // initial render, kept live by the 'message.read' broadcast below.
    var readState = {};
    try {
        JSON.parse(msgs.dataset.readState || '[]').forEach(function (s) {
            readState[s.user_id] = {
                lastReadMessageId: s.last_read_message_id,
                readAt: s.last_read_at ? new Date(s.last_read_at) : null,
            };
        });
    } catch (e) { /* malformed/missing state — treat as nobody has read anything yet */ }

    var markReadUrl = msgs.dataset.markReadUrl;

    function seenState(messageId) {
        var readers = Object.keys(readState);
        if (! readers.length) return { seen: false, at: null };

        var seenAt = null;
        for (var i = 0; i < readers.length; i++) {
            var s = readState[readers[i]];
            if (! s.lastReadMessageId || s.lastReadMessageId < messageId) return { seen: false, at: null };
            if (s.readAt && (! seenAt || s.readAt > seenAt)) seenAt = s.readAt;
        }

        return { seen: true, at: seenAt };
    }

    function renderTicks(row) {
        var ticks = row.querySelector('.msg-ticks');
        if (! ticks) return;

        var state = seenState(parseInt(row.dataset.messageId, 10));
        if (state.seen) {
            var h = String(state.at ? state.at.getHours() : '').padStart(2, '0');
            var m = String(state.at ? state.at.getMinutes() : '').padStart(2, '0');
            ticks.textContent = '✓✓' + (state.at ? ' ' + h + ':' + m : '');
            ticks.style.color = '#34b7f1';
        } else {
            ticks.textContent = '✓';
            ticks.style.color = '';
        }
    }

    function renderAllTicks() {
        msgs.querySelectorAll('.msg.me[data-message-id]').forEach(renderTicks);
    }

    renderAllTicks();

    /** Fire-and-forget — "seen" is a courtesy signal to the other side, not a UI-blocking call. */
    function markSeen() {
        if (! markReadUrl || document.hidden) return;

        fetch(markReadUrl, {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
        }).catch(function () {});
    }

    document.addEventListener('visibilitychange', function () {
        if (! document.hidden) markSeen();
    });

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
            channel.bind('message.new', function (payload) {
                appendMessage(payload);
                if (payload.sender_id !== actorId) markSeen();
            });
            channel.bind('message.deleted', function (payload) { markDeleted(payload.id); });
            channel.bind('message.read', function (payload) {
                readState[payload.user_id] = {
                    lastReadMessageId: payload.last_read_message_id,
                    readAt: payload.read_at ? new Date(payload.read_at) : null,
                };
                renderAllTicks();
            });
            channel.bind('pusher:subscription_succeeded', function () { poll(); markSeen(); });
        };
        pusherScript.onerror = poll;
        document.head.appendChild(pusherScript);
    } else {
        // No Pusher key configured yet — still sync once so the page isn't stuck at
        // whatever it looked like at the moment it was loaded.
        poll();
        markSeen();
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
