# Agency OS — Smooth Navigation Pass

Implemented a subtle navigation-continuity layer for Laravel screens and all approved prototype screens.

- Same-origin page changes use the browser View Transitions API when available.
- Fallback uses a very light 85 ms opacity shift plus a 2 px orange progress line.
- Locale POST forms preserve the clicked `locale` button name/value.
- Login, logout and other normal form submissions remain server-driven.
- Prototype language, role switching, logout and programmatic navigation use the same behavior.
- Modified-clicks, external links, downloads, anchors and new tabs are never intercepted.
- `prefers-reduced-motion` disables animation.

This is intentionally not a visible slide/fade transition; it only removes the harsh white flash between pages.
