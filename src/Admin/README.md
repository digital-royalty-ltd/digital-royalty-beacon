# Admin

This directory contains the WordPress admin layer for Beacon.

The admin architecture is intentionally separated into clear responsibilities:

Admin/
Actions/
Components/
Config/
Screens/
Views/


## Structure

### Actions
Handles `admin_post_*` requests.

Responsible for:
- Capability checks
- Nonce validation
- Input sanitization
- Calling services (e.g. API client)
- Mutating state (creating posts, updating options, etc.)
- Redirects

Actions never render UI.

---

### Components
Reusable UI fragments used by Views.

- Rendering-only
- No routing awareness
- No state mutations

Examples: tables, badges, cards, notices.

---

### Config
Admin configuration and wiring.

Currently includes:
- `AdminMenu` (registers WordPress menus using the ScreenRegistry)

No rendering logic belongs here.

---

### Screens
Defines WordPress admin screens and menu structure.

Responsible for:
- Slugs
- Parent-child relationships
- Capabilities
- Delegating rendering to Views

Screens do not contain business logic.

---

### Views
Full admin page renderers.

Responsible for:
- Markup output
- Composing Components
- Read-only data access

Views must not mutate state directly.
All mutations are delegated to Actions.

---

## Request Flow

1. WordPress loads `admin.php?page=...`
2. A Screen resolves the request
3. The Screen delegates to a View
4. Forms submit to `admin-post.php`
5. An Action handles the mutation and redirects

---

## Design Principles

- Clear separation of concerns
- No rendering in Actions
- No mutations in Views
- Centralized screen registration
- Predictable growth as features expand