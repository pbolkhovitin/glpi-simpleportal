# SimplePortal — GLPI Plugin

Anonymous ticket submission portal for GLPI 11.x with admin configuration page.

## Features

- Public ticket submission form (no authentication required)
- Category selection from active helpdesk ITIL categories
- Email confirmation on successful submission
- Admin config page (settings + technical info panel)
- CSRF-protected form
- GLPI-themed Twig-based interface

## Requirements

- GLPI ≥ 11.0
- PHP ≥ 8.1

## Installation

1. Clone into `plugins/simpleportal`:

```bash
git clone https://github.com/pbolkhovitin/glpi-simpleportal.git plugins/simpleportal
```

2. Install and activate from GLPI's _Setup → Plugins_ page:

```bash
php bin/console plugin:install simpleportal
php bin/console plugin:activate simpleportal
```

3. Configure in _Setup → Plugins → SimplePortal → gear icon_:
   - **Default entity** — entity for new tickets
   - **Ticket type** — incident (default) or request
   - **Notification** — enable/disable confirmation email
   - **Sender email** — `From:` address for notifications

## Usage

```
http://your-glpi-instance/plugins/simpleportal/
```

## Technical Notes

- Plugin uses Symfony routing (`#[Route]`, `#[SecurityStrategy]`) and Twig templates
- API session init skips `App-Token` for localhost API calls (GLPIKey decrypt fails on unencrypted token)
- CSRF token obtained from `<meta property="glpi:csrf_token">` in login page
- Plugin assets in `public/` are served statically at `/plugins/simpleportal/`

## License

GNU General Public License v3 or later (GPLv3+)
