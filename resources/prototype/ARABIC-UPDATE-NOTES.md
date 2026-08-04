# Agency OS Arabic / English UI Update

## Implemented
- Arabic mode uses full RTL across the interface.
- English mode uses LTR.
- Visible mock data is localized to Arabic only while Arabic is selected, including names, departments, clients, projects, tasks, notifications, comments, reports, and audit descriptions.
- English remains the canonical demo-data language.
- The selected language is saved in LocalStorage.
- The language control is a two-sided switch: EN on the left and العربية on the right.
- Orange and White are declared as the two primary brand colors in the shared CSS tokens.

## Main changed files
- assets/guards.js
- assets/agencyos.js
- assets/agencyos.css
- assets/locale-patch.js (new)
- login.html
- all HTML screens now load assets/locale-patch.js
- README.md
- CHANGELOG.md

## Validation
- JavaScript syntax checked for all shared asset files.
- JavaScript syntax checked for all 46 inline page scripts.
- All 51 HTML screens reference the Arabic localization patch.
