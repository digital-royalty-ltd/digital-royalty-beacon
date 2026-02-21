# Database

This directory contains the plugin-managed database schema.

Each `*Table` class is responsible for:
- Defining the table name (using `$wpdb->prefix`)
- Creating or updating the schema via `dbDelta()`
- Managing a schema version option for safe upgrades

Tables are installed on activation, and also defensively during admin boot (some hosts skip activation hooks).

## Current Tables

- `LogsTable`  
  Stores plugin operational logs, request IDs, and report-related events.

- `ReportsTable`  
  Stores report records and their processing state for onboarding and scanning workflows.

## Pattern

A typical table class provides:

- `tableName(wpdb $wpdb): string`
- `install(): void` (version check + existence check + `dbDelta`)
- `createOrUpdateTable(wpdb $wpdb): void`

Schema versions are tracked via an option (see `Support/Enums/Database/*TableEnum`).

## Conventions

- Always use `$wpdb->prefix` for table names
- Use `dbDelta()` to apply schema changes
- Bump schema version when changing SQL
- Keep schema changes backward compatible where possible
- Add indexes for frequent queries and admin listing screens
