    @php
        // Role-scoped starter prompts (BRD-free, just UX) — one set per role, matching
        // the tools that role's GroqService::toolDefinitions() actually offers, so a
        // starter is never a dead end. Employee is the fallback for guests/no role.
        $starters = __('agencyos.assistant.starters.'.(auth()->user()?->roleCode()->value ?? 'employee'));
        $starters = is_array($starters) ? $starters : [];
    @endphp
    <div class="gm-wrap" id="gm-wrap">
        <button type="button" class="gm-bubble" id="gm-toggle" aria-haspopup="dialog" aria-expanded="false" aria-controls="gm-panel">
            <span class="gm-glass">
                <span class="gm-smoke gm-smoke-a"></span>
                <span class="gm-smoke gm-smoke-b"></span>
                <x-icon name="message-circle" class="gm-star"/>
            </span>
        </button>
        <div class="gm-panel hide" id="gm-panel" role="dialog" aria-label="{{ __('agencyos.assistant.title') }}">
            <img class="gm-mascot" id="gm-mascot" src="{{ asset('images/mascot-zaatar-leaning.png') }}" alt="">
            <div class="gm-panel-body">
                <div class="gm-head">
                    <span>{{ __('agencyos.assistant.title') }}</span>
                    <button type="button" class="gm-close" id="gm-close" aria-label="{{ __('agencyos.assistant.close') }}"><x-icon name="x"/></button>
                </div>
                <div class="gm-msgs" id="gm-msgs">
                    <div class="gm-msg gm-model"><div class="gm-bub">{{ __('agencyos.assistant.greeting') }}</div></div>
                    @if($starters)
                        <div class="gm-suggestions" id="gm-initial-suggestions">
                            @foreach($starters as $starter)
                                <button type="button" class="gm-suggestion">{{ $starter }}</button>
                            @endforeach
                        </div>
                    @endif
                </div>
                <form class="gm-input" id="gm-form">
                    <input type="text" id="gm-text" maxlength="2000" autocomplete="off" placeholder="{{ __('agencyos.assistant.placeholder') }}">
                    <button type="submit" class="btn btn-primary btn-sm" id="gm-send">{{ __('agencyos.assistant.send') }}</button>
                </form>
            </div>
        </div>
    </div>
    <script>
    (function () {
        var wrap = document.getElementById('gm-wrap');
        var toggle = document.getElementById('gm-toggle');
        var panel = document.getElementById('gm-panel');
        var closeBtn = document.getElementById('gm-close');
        var msgs = document.getElementById('gm-msgs');
        var form = document.getElementById('gm-form');
        var input = document.getElementById('gm-text');
        var sendBtn = document.getElementById('gm-send');
        var mascot = document.getElementById('gm-mascot');
        if (!wrap) return;

        var IDLE_SRC = {{ \Illuminate\Support\Js::from(asset('images/mascot-zaatar-leaning.png')) }};
        var THINKING_SRC = {{ \Illuminate\Support\Js::from(asset('images/mascot-zaatar-thinking.png')) }};
        // Both poses are decoded into the browser's cache up front, so the very
        // first swap is instant instead of waiting on a fresh fetch+decode.
        [IDLE_SRC, THINKING_SRC].forEach(function (src) { (new Image()).src = src; });

        var csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

        // Conversation survives page reloads via localStorage, but only for 24h — after
        // that (or in a fresh browser) it starts clean. Scoped per user so a shared
        // machine doesn't leak one person's chat into another's session.
        var STORAGE_KEY = {{ \Illuminate\Support\Js::from('agencyos-karen-chat-'.(auth()->id() ?? 'guest')) }};
        var STORAGE_TTL_MS = 24 * 60 * 60 * 1000;

        function loadHistory() {
            try {
                var raw = localStorage.getItem(STORAGE_KEY);
                if (!raw) return [];
                var saved = JSON.parse(raw);
                if (!saved || !Array.isArray(saved.messages) || typeof saved.savedAt !== 'number') return [];
                if (Date.now() - saved.savedAt > STORAGE_TTL_MS) {
                    localStorage.removeItem(STORAGE_KEY);
                    return [];
                }
                return saved.messages;
            } catch (e) {
                return [];
            }
        }

        function saveHistory() {
            try {
                localStorage.setItem(STORAGE_KEY, JSON.stringify({ messages: history, savedAt: Date.now() }));
            } catch (e) {}
        }

        var history = loadHistory();

        function open() {
            panel.classList.remove('closing');
            panel.classList.remove('hide');
            toggle.setAttribute('aria-expanded', 'true');
            input.focus();
        }
        function close() {
            if (panel.classList.contains('hide') || panel.classList.contains('closing')) return;
            toggle.setAttribute('aria-expanded', 'false');
            panel.classList.add('closing');
            panel.addEventListener('animationend', function onEnd() {
                panel.removeEventListener('animationend', onEnd);
                panel.classList.remove('closing');
                panel.classList.add('hide');
            });
        }

        toggle.addEventListener('click', function () {
            panel.classList.contains('hide') ? open() : close();
        });
        closeBtn.addEventListener('click', close);

        function addMessage(role, text) {
            var row = document.createElement('div');
            row.className = 'gm-msg ' + (role === 'user' ? 'gm-user' : 'gm-model');
            var bub = document.createElement('div');
            bub.className = 'gm-bub';
            bub.textContent = text;
            row.appendChild(bub);
            msgs.appendChild(row);
            msgs.scrollTop = msgs.scrollHeight;
            return row;
        }

        // Quick-reply buttons under the latest AI message — the model appends a
        // machine-parsed suggestions line to every reply (see GroqService), stripped
        // out server-side and sent here as a plain list. Old buttons are replaced,
        // never stacked, so only the most recent turn's suggestions are ever tappable.
        function renderSuggestions(list) {
            var old = msgs.querySelector('.gm-suggestions');
            if (old) old.remove();
            if (!list || !list.length) return;

            var wrap = document.createElement('div');
            wrap.className = 'gm-suggestions';
            list.forEach(function (q) {
                var btn = document.createElement('button');
                btn.type = 'button';
                btn.className = 'gm-suggestion';
                btn.textContent = q;
                wrap.appendChild(btn);
            });
            msgs.appendChild(wrap);
            msgs.scrollTop = msgs.scrollHeight;
        }

        // Delegated so it covers both the server-rendered starter buttons (present at
        // load) and every batch renderSuggestions() adds later, with one listener.
        msgs.addEventListener('click', function (e) {
            var btn = e.target.closest('.gm-suggestion');
            if (!btn || input.disabled) return;
            input.value = btn.textContent;
            form.requestSubmit ? form.requestSubmit() : form.dispatchEvent(new Event('submit', { cancelable: true }));
        });

        history.forEach(function (turn) {
            addMessage(turn.role === 'model' ? 'model' : 'user', turn.text);
        });

        // The starter buttons only make sense on a genuinely fresh conversation —
        // once there's saved history, they'd float above old messages out of context.
        if (history.length) {
            var initialSuggestions = document.getElementById('gm-initial-suggestions');
            if (initialSuggestions) initialSuggestions.remove();
        }

        form.addEventListener('submit', function (e) {
            e.preventDefault();
            var text = input.value.trim();
            if (!text) return;

            renderSuggestions(null);
            addMessage('user', text);
            input.value = '';
            input.disabled = true;
            sendBtn.disabled = true;
            var thinking = addMessage('model', '{{ __('agencyos.assistant.thinking') }}');
            thinking.classList.add('gm-thinking');
            if (mascot) mascot.src = THINKING_SRC;

            // The model often replies in well under a second, which would swap the
            // thinking pose back to idle before a human eye can register it. This
            // guarantees the thinking pose stays up for at least MIN_THINKING_MS
            // regardless of how fast the real reply arrives.
            var MIN_THINKING_MS = 500;
            var thinkingStartedAt = Date.now();
            var minThinkingDelay = function () {
                var elapsed = Date.now() - thinkingStartedAt;
                return elapsed >= MIN_THINKING_MS ? Promise.resolve() : new Promise(function (resolve) {
                    setTimeout(resolve, MIN_THINKING_MS - elapsed);
                });
            };

            fetch({{ \Illuminate\Support\Js::from(route('assistant.chat')) }}, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                },
                body: JSON.stringify({ message: text, history: history }),
            })
                .then(function (res) { return res.json().then(function (data) { return { ok: res.ok, data: data }; }); })
                .then(function (result) { return minThinkingDelay().then(function () { return result; }); })
                .then(function (result) {
                    thinking.remove();
                    if (!result.ok) {
                        addMessage('model', result.data.error || '{{ __('agencyos.assistant.error') }}');
                        return;
                    }
                    addMessage('model', result.data.reply);
                    renderSuggestions(result.data.suggestions);
                    history.push({ role: 'user', text: text });
                    history.push({ role: 'model', text: result.data.reply });
                    if (history.length > 20) history = history.slice(-20);
                    saveHistory();
                })
                .catch(function () {
                    thinking.remove();
                    addMessage('model', '{{ __('agencyos.assistant.error') }}');
                })
                .finally(function () {
                    input.disabled = false;
                    sendBtn.disabled = false;
                    input.focus();
                    if (mascot) mascot.src = IDLE_SRC;
                });
        });

        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && !panel.classList.contains('hide')) close();
        });
    })();
    </script>
