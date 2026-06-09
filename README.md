# SimplePortal — GLPI Plugin

Anonymous ticket submission portal for GLPI 11.x.

## Features

- Public ticket submission form (no authentication required)
- Category selection from active ITIL categories
- Email confirmation on successful submission
- GLPI API-based ticket creation
- Clean GLPI-themed interface

## Requirements

- GLPI ≥ 11.0
- PHP ≥ 8.1
- curl extension enabled
- GLPI REST API enabled with an API client configured

## Installation

1. Clone the repository into the `plugins/simpleportal` directory of your GLPI installation:

```bash
git clone https://github.com/pbolkhovitin/glpi-simpleportal.git plugins/simpleportal
```

2. Install and activate the plugin from GLPI's plugin management interface.

3. Configure the API credentials:
   - Go to _Configuration → Plugins → SimplePortal_
   - Enter the API URL (e.g., `http://localhost/apirest.php`)
   - Enter the App Token of your API client
   - Enter the User Token if required

## Usage

The portal is available at:
```
http://your-glpi-instance/plugins/simpleportal/
```

## License

GNU General Public License v3 or later (GPLv3+)
