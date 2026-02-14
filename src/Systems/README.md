# Systems

This directory contains Beacon’s core application logic.

Systems implement domain behavior and orchestrate work between:
- Services
- Repositories
- External APIs
- WordPress

Systems/
Api/
Reports/


## Api

Contains the Beacon API client and related integration logic.

Responsible for:
- Communicating with the Digital Royalty dashboard
- Structuring outbound requests
- Handling API responses

This layer does not deal with admin UI.

---

## Reports

Implements the report lifecycle and onboarding workflow.

Responsible for:
- Determining required reports
- Managing report state
- Submitting reports via the API
- Coordinating retries and status checks

Business rules live here, not in Views or Controllers.

---

## Design Notes

- Systems contain application logic.
- They may depend on Services and Repositories.
- They must not render UI.
- They should remain framework-light and testable.