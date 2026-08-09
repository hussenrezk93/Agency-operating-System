@props(['name'])

@php
    /**
     * Self-hosted, Lucide-equivalent line icons (no CDN dependency). 24x24 viewBox,
     * stroke-based to match Lucide's visual language exactly. Sizing is controlled
     * entirely by the wrapping `.ic`/`.avatar`-style element via `.ic svg{width/height:100%}`
     * in agencyos.css, so every existing icon slot in the app picks these up automatically.
     */
    $paths = [
        'home' => '<path d="M3 10.5 12 3l9 7.5"/><path d="M5 9.5V21h14V9.5"/><path d="M9 21v-6h6v6"/>',
        'list-checks' => '<path d="m3 6 1.5 1.5L7.5 4.5"/><path d="m3 13 1.5 1.5L7.5 11.5"/><path d="m3 20 1.5 1.5L7.5 18.5"/><path d="M12 6h9"/><path d="M12 13h9"/><path d="M12 20h9"/>',
        'folder-kanban' => '<path d="M3 6a2 2 0 0 1 2-2h4l2 2h8a2 2 0 0 1 2 2v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V6Z"/><path d="M8 12v4"/><path d="M12 10v6"/><path d="M16 13v3"/>',
        'star' => '<path d="M12 2.5 15 9l7 1-5.2 5 1.3 7-6.1-3.4L5.9 22l1.3-7-5.2-5 7-1L12 2.5Z"/>',
        'bar-chart-3' => '<path d="M4 21V9"/><path d="M12 21V4"/><path d="M20 21v-7"/><path d="M3 21h18"/>',
        'users' => '<circle cx="9" cy="8" r="3.3"/><path d="M2.5 20a6.5 6.5 0 0 1 13 0"/><path d="M17 8.2a3.3 3.3 0 1 1 1.2 6.4"/><path d="M15.5 14.3a6.5 6.5 0 0 1 6 5.7"/>',
        'building-2' => '<path d="M6 21V4a1 1 0 0 1 1-1h6a1 1 0 0 1 1 1v17"/><path d="M14 9h5a1 1 0 0 1 1 1v11"/><path d="M3 21h18"/><path d="M9 7h.01M9 11h.01M9 15h.01M17 13h.01M17 17h.01"/>',
        'clock' => '<circle cx="12" cy="12" r="9.3"/><path d="M12 7v5.5l3.6 2.2"/>',
        'message-circle' => '<path d="M12 3.5c-5 0-9 3.4-9 7.6 0 2.5 1.4 4.7 3.6 6.1-.15 1.1-.6 2.3-1.6 3.3 1.7 0 3.3-.6 4.5-1.5.8.2 1.6.3 2.5.3 5 0 9-3.4 9-7.6s-4-8.2-9-8.2Z"/>',
        'shuffle' => '<path d="m18 4 3 3-3 3"/><path d="M2 7h4.2a4 4 0 0 1 3.4 1.9L13 15a4 4 0 0 0 3.4 1.9H21"/><path d="m18 20 3-3-3-3"/><path d="M2 17h4.2a4 4 0 0 0 3.4-1.9l.4-.7"/><path d="M13.5 9.1 14 8.4A4 4 0 0 1 17.4 6.5H21"/>',
        'layout-grid' => '<rect x="3.5" y="3.5" width="7.5" height="7.5" rx="1.3"/><rect x="13" y="3.5" width="7.5" height="7.5" rx="1.3"/><rect x="3.5" y="13" width="7.5" height="7.5" rx="1.3"/><rect x="13" y="13" width="7.5" height="7.5" rx="1.3"/>',
        'settings' => '<circle cx="12" cy="12" r="3.2"/><path d="M19.4 13.5a1.7 1.7 0 0 0 .3 1.9l.1.1a2 2 0 1 1-2.9 2.9l-.1-.1a1.7 1.7 0 0 0-1.9-.3 1.7 1.7 0 0 0-1 1.6v.2a2 2 0 1 1-4 0v-.1a1.7 1.7 0 0 0-1.1-1.6 1.7 1.7 0 0 0-1.9.3l-.1.1a2 2 0 1 1-2.9-2.9l.1-.1a1.7 1.7 0 0 0 .3-1.9 1.7 1.7 0 0 0-1.6-1h-.2a2 2 0 1 1 0-4h.1A1.7 1.7 0 0 0 4.1 8a1.7 1.7 0 0 0-.3-1.9l-.1-.1a2 2 0 1 1 2.9-2.9l.1.1a1.7 1.7 0 0 0 1.9.3H8.7A1.7 1.7 0 0 0 9.7 2v-.2a2 2 0 1 1 4 0v.1a1.7 1.7 0 0 0 1 1.6 1.7 1.7 0 0 0 1.9-.3l.1-.1a2 2 0 1 1 2.9 2.9l-.1.1a1.7 1.7 0 0 0-.3 1.9v.1a1.7 1.7 0 0 0 1.6 1h.2a2 2 0 1 1 0 4h-.1a1.7 1.7 0 0 0-1.6 1Z"/>',
        'shield-check' => '<path d="M12 2.5 4.5 5.5v6c0 5 3.2 8.4 7.5 10 4.3-1.6 7.5-5 7.5-10v-6L12 2.5Z"/><path d="m9 12 2 2 4-4.2"/>',
        'search' => '<circle cx="11" cy="11" r="7.2"/><path d="m21 21-4.6-4.6"/>',
        'check-circle' => '<circle cx="12" cy="12" r="9.3"/><path d="m8.3 12.3 2.5 2.5 5-5.2"/>',
        'bell' => '<path d="M6.5 9.5a5.5 5.5 0 0 1 11 0c0 4.5 1.5 6 2 6.5H4.5c.5-.5 2-2 2-6.5Z"/><path d="M9.7 19.5a2.3 2.3 0 0 0 4.6 0"/>',
        'user' => '<circle cx="12" cy="8" r="3.6"/><path d="M4.5 20.5a7.5 7.5 0 0 1 15 0"/>',
        'menu' => '<path d="M3.5 6.5h17"/><path d="M3.5 12h17"/><path d="M3.5 17.5h17"/>',
        'log-out' => '<path d="M9.5 21H5.5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><path d="M16.5 16.5 21 12l-4.5-4.5"/><path d="M21 12H9.5"/>',
        'globe' => '<circle cx="12" cy="12" r="9.3"/><path d="M2.7 12h18.6"/><path d="M12 2.7a15 15 0 0 1 0 18.6"/><path d="M12 2.7a15 15 0 0 0 0 18.6"/>',
        'alert-triangle' => '<path d="M12 3.2 2.2 20.5h19.6L12 3.2Z"/><path d="M12 10v4"/><path d="M12 17.3h.01"/>',
        'edit' => '<path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4Z"/>',
        'trash' => '<path d="M4 7h16"/><path d="M9 7V4.5A1.5 1.5 0 0 1 10.5 3h3A1.5 1.5 0 0 1 15 4.5V7"/><path d="M6.5 7 7.3 19a2 2 0 0 0 2 1.9h5.4a2 2 0 0 0 2-1.9L17.5 7"/><path d="M10 11v6"/><path d="M14 11v6"/>',
        'plus' => '<path d="M12 5v14"/><path d="M5 12h14"/>',
        'chevron-down' => '<path d="m6 9 6 6 6-6"/>',
        'chevron-up' => '<path d="m6 15 6-6 6 6"/>',
        'chevron-right' => '<path d="m9 6 6 6-6 6"/>',
        'eye' => '<path d="M2.5 12S6 5.5 12 5.5 21.5 12 21.5 12 18 18.5 12 18.5 2.5 12 2.5 12Z"/><circle cx="12" cy="12" r="3"/>',
        'download' => '<path d="M12 3.5v11.5"/><path d="m7 11 5 5 5-5"/><path d="M4.5 19.5h15"/>',
        'filter' => '<path d="M4 5h16"/><path d="M7 12h10"/><path d="M10.5 19h3"/>',
        'x' => '<path d="M6 6l12 12"/><path d="M18 6 6 18"/>',
        'arrow-right' => '<path d="M4.5 12h15"/><path d="m13.5 6 6 6-6 6"/>',
        'calendar' => '<rect x="3.5" y="5" width="17" height="16" rx="2.2"/><path d="M8 3v4"/><path d="M16 3v4"/><path d="M3.5 10h17"/>',
        'mail' => '<rect x="2.5" y="5" width="19" height="14" rx="2.2"/><path d="m3.5 6.5 8.5 7 8.5-7"/>',
        'sparkles' => '<path d="M12 3v3.5"/><path d="M12 17.5V21"/><path d="M3 12h3.5"/><path d="M17.5 12H21"/><path d="m5.5 5.5 2.5 2.5"/><path d="m16 16 2.5 2.5"/><path d="m18.5 5.5-2.5 2.5"/><path d="m8 16-2.5 2.5"/><circle cx="12" cy="12" r="2.3"/>',
    ];

    $svg = $paths[$name] ?? $paths['star'];
@endphp
<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" {{ $attributes }}>{!! $svg !!}</svg>
