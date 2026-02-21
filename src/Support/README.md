# Support

Shared infrastructure utilities used across the plugin.

Support/
Autoloader.php
Logger.php
Enums/
Helpers/


## Autoloader

Registers the plugin's PSR-4 style autoloading.

All classes under `DigitalRoyalty\Beacon\` are resolved from `src/`.

---

## Logger

Lightweight logging utility backed by the plugin's database table.

Used for:
- Operational events
- Debugging
- Report lifecycle tracking

---

## Enums

Centralised constants used across the system.

Examples include:
- Admin page slugs
- Option keys
- Table schema versions
- Action names

Enums prevent magic strings and keep identifiers consistent.

---

## Helpers

Small utility classes that do not belong to a specific domain.

Currently includes:
- `AdminUrl` (admin URL builder)

Helpers should remain stateless and framework-agnostic where possible.