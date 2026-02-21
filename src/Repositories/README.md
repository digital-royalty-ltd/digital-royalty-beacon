# Repositories

This directory contains data access classes for Beacon.

Repositories encapsulate database reads and writes for plugin tables (via `wpdb`), keeping SQL out of Views, Screens, and Actions.

## Responsibilities

- Query plugin tables (e.g. logs, reports)
- Provide focused methods for common operations
- Return simple arrays or lightweight DTO-style structures
- Keep table name resolution centralized (typically via `*Table::tableName($wpdb)`)

## Conventions

- Inject `wpdb` via the constructor
- Use `$wpdb->prepare()` for all dynamic SQL
- Prefer explicit methods over generic query builders
- Do not render UI or perform admin routing from repositories
- Keep business logic in Services/Systems, not in repositories

## Examples

- `ReportsRepository`  
  Reads and updates report rows used by onboarding and the reports system.
