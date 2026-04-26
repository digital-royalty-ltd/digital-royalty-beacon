# WP Beacon Plugin TODO

## Current Status

The plugin is at v0.3.0 and is functionally operational. Core API integration, deferred job processing, 20+ Workshop optimisation tools, admin React SPA, reporting system, and public API are all working. Active development on Workshop tools and onboarding UX.

## Remaining Work

- [ ] Build out Campaigns page (currently stub-level)
- [ ] Complete deeper integration for PostTypeSwitcher, ClonePost, and MediaReplace features
- [ ] Add automated tests (none currently)
- [ ] Review and finalise public API documentation auto-generation
- [ ] Add more action invokers as Laravel adds adapter-transport actions to `config/beacon-actions.php` (e.g. wp.post.update_excerpt, wp.post.publish_draft)
- [ ] Wire signal results into report generators where it makes sense (e.g. backlink summary feeding the SEO audit report)

## Automations

| Automation | Type | Status |
|---|---|---|
| Content Generator | Interactive tool | [x] Complete |
| Content From Sample | Interactive tool | [x] Complete |
| Gap Analysis | Background (deferred) | [x] Complete |
| Image Generator | Interactive tool | [x] Complete |
| News Article Generator | Interactive tool (chained) | [x] Complete |
| Social Media Sharer | Interactive + scheduled | [x] Complete (API integrations pending) |

## Insights (signals from Laravel)

| Insight | Source | Status |
|---|---|---|
| Backlink Summary | DataForSEO | [x] Tile available |
| Keyword Suggestions | DataForSEO | [x] Tile available |
| SERP Snapshot | DataForSEO | [x] Tile available |
| Top Search Queries | GSC (per-project OAuth) | [x] Tile available |
| Top Pages | GSC (per-project OAuth) | [x] Tile available |

## Actions (adapter-transport invokers)

| Action slug | Status |
|---|---|
| wp.page.update_meta | [x] Implemented |
| wp.post.add_internal_link | [x] Implemented |
| wp.post.update_excerpt | [ ] Pending Laravel registry entry |
| wp.post.publish_draft | [ ] Pending Laravel registry entry |
