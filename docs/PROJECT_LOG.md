# LEMS SCNMTC Backend - Project Log

## Current state
- Phase: 1 - Foundation, step 1 (skeleton) built, waiting for owner review/commit
- Branch: `phase-1/skeleton` (branched from `chore/project-setup`)
- Done so far: project brief, gitignore, log, Laravel 13 skeleton with Pest, Larastan, Pint, `composer ci`, pre-push hook, health test
- Next: step 2 - error envelope `{message, errors, code}` and readiness endpoint
- Waiting on owner: review and commit step 1; D1 final answer; Redis decision

## Decisions (D1-D7 from setup.md)
| # | Decision | Status | Answer | ADR |
|---|---|---|---|---|
| D1 | Frontend auth | Open | Leaning bearer tokens; same parent domain likely but unconfirmed | - |
| D2 | Production server | Open | Not needed until staging deploy | - |
| D3 | Frontend payload shapes | Open | Ask frontend team | - |
| D4 | Data migration | Open | None assumed | - |
| D5 | Grading scale | Open | Must come from the college | - |
| D6 | SMS provider | Open | Email first, SMS behind interface | - |
| D7 | Repo host | Decided | GitHub | - |

## Log

### 2026-11-18 - Setup and environment choices
**Branch:** `main` | **Phase:** 0 - Setup
**Done:** read the brief; checked tooling (PHP 8.5.12, Composer 2.10.3, Docker 29.8, WSL; XAMPP MySQL listening on 3306); created `CLAUDE.md`, the `/log-progress` skill and this log.
**Decided:** run PHP locally on 8.5; use XAMPP MySQL with database `lems_backend`; repo on GitHub; commits are short with no co-author line. Redis not decided: nothing is listening on 6379 and phpredis is not enabled.
**Needs owner:** Redis choice (skip for now, or Docker on 6379); D1; go-ahead to start Phase 1.
**Next:** Phase 1 step 1, project skeleton, once approved.

### 2026-11-18 - Phase 1 step 1: project skeleton
**Branch:** `phase-1/skeleton` | **Phase:** 1 - Foundation
**Done:** scaffolded Laravel 13 (PHP 8.5 locally, requires 8.3+), API only: removed web routes, views and frontend tooling; API served under `/api/v1` (`routes/api.php`, `bootstrap/app.php`); health check at `/up`. Added Pest 4, Larastan (level 6, `phpstan.neon`), Pint. `composer ci` runs Pint, Larastan and Pest. Pre-push hook in `.githooks/pre-push` (enable with `git config core.hooksPath .githooks`). Tests run on MySQL database `lems_backend_test` (created in XAMPP) so they match production. `.env.example` points at XAMPP MySQL. README with Windows setup.
**Decided:** Laravel 13 chosen over the brief's 11/12 (newest, longest support; owner approved). No docker-compose for now since MySQL comes from XAMPP. Cache, queue and session use database drivers; `REDIS_CLIENT=predis` is set but Predis is not installed yet. App timezone stays UTC; Africa/Accra conversion is left to display logic.
**Needs owner:** review and commit; confirm D1 when known.
**Next:** step 2 - error handling envelope and readiness endpoint.
