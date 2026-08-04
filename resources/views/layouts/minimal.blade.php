<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ app()->isLocale('ar') ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light">
    <meta name="theme-color" content="#202020">
    <link rel="icon" type="image/png" sizes="64x64" href="{{ asset('images/agencyos-favicon-64.png') }}">
    <link rel="apple-touch-icon" sizes="180x180" href="{{ asset('images/agencyos-apple-touch-icon.png') }}">
    <title>{{ __('agencyos.common.system_name') }} — @yield('title', __('agencyos.dashboard.page_title'))</title>
    <style>
        :root {
            /* Agency OS primary identity: orange + white. */
            --primary-orange: #f97316;
            --primary-orange-hover: #ea580c;
            --primary-orange-soft: #fff7ed;
            --primary-white: #ffffff;
            --surface: #fffaf6;
            --text: #18212f;
            --muted: #687386;
            --border: #fed7aa;
            --danger: #dc2626;
            --success: #15803d;
            --shadow: 0 22px 65px rgba(124, 45, 18, .13);
        }

        * { box-sizing: border-box; }

        html { min-height: 100%; background: var(--surface); }

        body {
            min-height: 100vh;
            margin: 0;
            color: var(--text);
            background:
                radial-gradient(circle at 8% 5%, rgba(249, 115, 22, .12), transparent 30rem),
                radial-gradient(circle at 92% 95%, rgba(249, 115, 22, .08), transparent 28rem),
                var(--surface);
            font-family: {{ app()->isLocale('ar') ? "Tahoma, Arial, 'Segoe UI', sans-serif" : "Inter, 'Segoe UI', Arial, sans-serif" }};
            line-height: 1.55;
        }

        button, input { font: inherit; }

        .page-shell {
            width: min(1120px, calc(100% - 28px));
            margin-inline: auto;
            padding-block: 24px 48px;
        }

        .topbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 18px;
            margin-block-end: 18px;
        }

        .brand {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            font-weight: 900;
            letter-spacing: -.02em;
        }

        .brand-mark {
            width: 44px;
            height: 47px;
            display: block;
            overflow: hidden;
            flex: 0 0 auto;
            border-radius: 12px;
            background: #202020;
            box-shadow: 0 8px 20px rgba(32, 32, 32, .20);
        }

        .brand-mark img {
            display: block;
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .locale-toggle {
            display: inline-grid;
            grid-template-columns: 1fr 1fr;
            padding: 4px;
            border: 1px solid var(--border);
            border-radius: 999px;
            background: var(--primary-white);
            box-shadow: 0 8px 24px rgba(124, 45, 18, .08);
            direction: ltr;
        }

        .locale-toggle button {
            min-width: 84px;
            border: 0;
            border-radius: 999px;
            padding: 8px 13px;
            color: var(--muted);
            background: transparent;
            font-size: 13px;
            font-weight: 800;
            cursor: pointer;
            transition: .18s ease;
        }

        .locale-toggle button:hover { color: var(--primary-orange-hover); }

        .locale-toggle button.is-active {
            color: var(--primary-white);
            background: var(--primary-orange);
            box-shadow: 0 5px 14px rgba(249, 115, 22, .3);
        }

        .card {
            background: var(--primary-white);
            border: 1px solid var(--border);
            border-radius: 24px;
            padding: clamp(22px, 4vw, 38px);
            box-shadow: var(--shadow);
            text-align: start;
        }

        .card--narrow {
            width: min(460px, 100%);
            margin-inline: auto;
        }

        h1, h2, h3, p { margin-block-start: 0; }
        h1 { font-size: clamp(24px, 4vw, 36px); line-height: 1.2; margin-block-end: 10px; }
        h2 { font-size: 19px; margin-block-end: 14px; }

        .eyebrow {
            color: var(--primary-orange-hover);
            font-size: 12px;
            font-weight: 900;
            letter-spacing: .08em;
            text-transform: uppercase;
            margin-block-end: 8px;
        }

        .muted { color: var(--muted); font-size: 14px; }

        label {
            display: block;
            font-weight: 800;
            font-size: 13px;
            margin-block: 14px 6px;
        }

        input {
            width: 100%;
            padding: 12px 14px;
            border: 1.5px solid #e5e7eb;
            border-radius: 12px;
            color: var(--text);
            background: var(--primary-white);
        }

        input:focus {
            outline: none;
            border-color: var(--primary-orange);
            box-shadow: 0 0 0 4px var(--primary-orange-soft);
        }

        .input-ltr { direction: ltr; text-align: left; }

        .primary-button {
            width: 100%;
            margin-block-start: 18px;
            padding: 12px 16px;
            border: 0;
            border-radius: 12px;
            color: var(--primary-white);
            background: var(--primary-orange);
            font-weight: 900;
            cursor: pointer;
            box-shadow: 0 8px 20px rgba(249, 115, 22, .22);
        }

        .primary-button:hover { background: var(--primary-orange-hover); }

        .secondary-button {
            border: 1px solid var(--border);
            border-radius: 11px;
            padding: 10px 14px;
            color: var(--primary-orange-hover);
            background: var(--primary-white);
            font-weight: 800;
            cursor: pointer;
        }

        .err, .ok {
            margin-block-start: 10px;
            padding: 10px 12px;
            border-radius: 10px;
            font-size: 13px;
            font-weight: 700;
        }

        .err { color: var(--danger); background: #fef2f2; }
        .ok { color: var(--success); background: #f0fdf4; }

        .hero-row {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 24px;
            margin-block-end: 28px;
        }

        .user-chip {
            min-width: 240px;
            padding: 14px 16px;
            border: 1px solid var(--border);
            border-radius: 16px;
            background: var(--primary-orange-soft);
        }

        .user-chip strong { display: block; color: var(--primary-orange-hover); }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 14px;
            margin-block-end: 28px;
        }

        .stat-card {
            padding: 18px;
            border: 1px solid #ffedd5;
            border-radius: 16px;
            background: linear-gradient(145deg, var(--primary-white), #fffaf5);
        }

        .stat-card .value {
            color: var(--primary-orange-hover);
            font-size: 30px;
            font-weight: 900;
            line-height: 1;
            margin-block: 8px;
        }

        .table-wrap {
            overflow-x: auto;
            border: 1px solid #ffedd5;
            border-radius: 16px;
        }

        table { width: 100%; border-collapse: collapse; min-width: 720px; }
        th, td { padding: 13px 15px; text-align: start; border-block-end: 1px solid #ffedd5; }
        th { color: #7c2d12; background: #fff7ed; font-size: 12px; }
        tr:last-child td { border-block-end: 0; }

        .badge {
            display: inline-flex;
            align-items: center;
            padding: 5px 9px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 800;
            white-space: nowrap;
        }

        .badge--orange { color: #9a3412; background: #ffedd5; }
        .badge--green { color: #166534; background: #dcfce7; }
        .badge--gray { color: #475569; background: #f1f5f9; }

        .dashboard-footer {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            margin-block-start: 22px;
            padding-block-start: 18px;
            border-block-start: 1px solid #ffedd5;
        }

        .dashboard-footer form { margin: 0; }

        @media (max-width: 760px) {
            .page-shell { width: min(100% - 18px, 1120px); padding-block-start: 14px; }
            .topbar, .hero-row, .dashboard-footer { align-items: stretch; flex-direction: column; }
            .locale-toggle { width: 100%; }
            .locale-toggle button { min-width: 0; }
            .stats-grid { grid-template-columns: 1fr; }
            .user-chip { min-width: 0; }
            .card { border-radius: 18px; padding: 20px; }
        }
    </style>
</head>
<body>
<div class="page-shell">
    <header class="topbar">
        <div class="brand" aria-label="Agency OS">
            <span class="brand-mark">
                <img src="{{ asset('images/agencyos-company-logo.png') }}" alt="" width="44" height="47">
            </span>
            <span>Agency OS</span>
        </div>

        <form class="locale-toggle" method="POST" action="{{ route('locale.update') }}" aria-label="{{ __('agencyos.language.switch_label') }}">
            @csrf
            <button type="submit" name="locale" value="en" class="{{ app()->isLocale('en') ? 'is-active' : '' }}" aria-pressed="{{ app()->isLocale('en') ? 'true' : 'false' }}">
                English
            </button>
            <button type="submit" name="locale" value="ar" class="{{ app()->isLocale('ar') ? 'is-active' : '' }}" aria-pressed="{{ app()->isLocale('ar') ? 'true' : 'false' }}">
                العربية
            </button>
        </form>
    </header>

    <main class="card @yield('card_class', 'card--narrow')">
        @yield('content')
    </main>
</div>
</body>
</html>
