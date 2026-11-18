# LEMS SCNMTC Backend - Project Log

## Current state
- Phase: 0 - Setup (Phase 1 not started; waiting for owner to say go)
- Branch: `main`
- Done so far: project brief (`setup.md`), `CLAUDE.md`, `/log-progress` skill, this log
- Next: owner approves Phase 1 plan, then `phase-1/skeleton` branch
- Waiting on owner: go-ahead for Phase 1; D1 final answer; Redis decision

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
