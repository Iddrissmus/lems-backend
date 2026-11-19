# 0006 - Local environment and database engine

Date: 2026-11-18 | Status: Accepted for local development, OPEN for staging and production | Decided by: Iddriss

## Context

The owner develops on Windows. The brief assumes MySQL 8, Redis and Docker. The owner already runs XAMPP, which ships **MariaDB 10.4**, not MySQL 8. There is no Redis installed.

## Decision

- Run PHP 8.5 natively on Windows. No Docker or Sail for local work.
- Use the XAMPP database server on port 3306: `lems_backend` for development and `lems_backend_test` for tests.
- **Tests run on a real MySQL-protocol database** (the test database above), never SQLite, so SQL behaviour matches reality. Each test run starts from freshly migrated tables.
- Cache, queue and sessions use the database driver. Redis is not needed yet. If it is added later: Docker on port 6379 and the Predis client (no PHP extension needed).
- The app timezone stays UTC; Africa/Accra equals UTC and only matters for display.

## Alternatives considered

- **Docker (Sail) for everything:** closest to production, but heavier on a laptop and unnecessary while the owner already has XAMPP.
- **SQLite for tests:** fast, but hides differences such as strict mode and JSON handling.

## Consequences

- **Open question:** the brief specifies MySQL 8 with strict mode. MariaDB 10.4 behaves slightly differently (JSON handling, some defaults). Staging and production must run the same engine as development. Decide which one (MySQL 8 is the brief's choice) before the staging server is built, and consider running MySQL 8 locally in Docker to test against it.
- XAMPP's database can be stopped unexpectedly. If tests or the pre-push hook fail with "connection refused", start it in the XAMPP control panel.
