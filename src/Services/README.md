# Services

This directory provides centralized access to shared application services.

Currently it contains a single `Services` facade responsible for:

- Creating and caching core service instances
- Reading required options (e.g. API key)
- Providing lightweight dependency wiring

## Current Services

- `Logger`
- `ApiClient`
- `ReportSubmitter`

Services are instantiated lazily and cached for the duration of the request.

## Notes

- This acts as a simple service container.
- Call `Services::reset()` after connect/disconnect to clear cached instances.
- Business logic belongs in `Systems/`; this layer only wires dependencies.
