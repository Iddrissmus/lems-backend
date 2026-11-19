# 0001 - Laravel 13, API only

Date: 2026-11-18 | Status: Accepted | Decided by: Iddriss

## Context

The project brief said "Laravel 11/12". By the time work started, Laravel 13 was the current release. The frontend is a separate application owned by another team, so this repository has no pages of its own.

## Decision

Use Laravel 13 on PHP 8.3 or newer (8.5 is used locally), as an API only: no views, no web routes, no frontend tooling. Everything is served under `/api/v1`.

## Alternatives considered

- **Laravel 12:** matches the brief word for word and is the most documented, but its support window ends sooner than 13's.
- **Laravel 11:** already past its bug-fix period.

## Consequences

- The longest support window for a system that will run for years.
- Some packages lag behind new Laravel versions. Each package was checked to install cleanly before use.
- Upgrading later means moving to Laravel 14, not catching up.
