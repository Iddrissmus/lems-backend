# 0012 - Demo decisions: sign-in, marks, permissions and the sample GPA

Date: 2026-11-21 | Status: Accepted for the Monday demo | Decided by: Iddriss (after the frontend engineer's reply)

## Context

The frontend engineer replied that our API owning the accounts can work, if the sign-in and security flows are agreed, and that student results should show letter grades and credit hours only, with any GPA calculated on an agreed sample scale and labelled not official. The marks structure, the permission names and the GPA source were open (ADR 0011, `docs/api/demo-contract.md` Q1, Q2, Q5).

## Decision

- **Sign-in:** our API owns the user accounts. The frontend server calls `POST /auth/login` and keeps the token in its own server session. For Monday it is password only: the API has no passkeys or 2FA, so those Fortify flows are not used. They are decided after the demo.
- **Marks:** one "Overall" mark out of 100 per student per course. The database already allows components with weights; they are not used until the College confirms its rules.
- **Permissions:** the frontend's colon style for every new permission (`marks:enter`, `marks:submit`, `results:release`, `results:view-own` and the others in the contract). Existing `users.*` and `audit.view` keep their names for now.
- **GPA:** the API never computes a GPA. It returns letter grade and credit hours per released course, and `GET /grade-scale` returns the active scale with a sample `points` value per letter, flagged `is_demo` and `official: false`. The frontend calculates a GPA from those and labels it "sample, not official". Percentage averages are never sent to students.

## Alternatives considered

- **The API calculates a sample GPA:** puts an invented number in our responses and risks it being read as official.
- **No GPA at all:** the frontend screens expect a GPA card; the labelled sample keeps them useful without pretending.
- **Components with weights:** more realistic but needs weights and rules the College has not confirmed, and more screens before Monday.
- **Keep the frontend's local login for some staff:** more adapters for the frontend and two user stores before Monday.

## Consequences

- The grade points are invented and exist only to make a demo; they must be replaced or removed when the College confirms its metric (D5). They sit in the demo seeder and the `grade_scale_bands.points` column, not in code.
- Because the frontend computes the GPA, no official GPA exists anywhere in the system yet.
- Passkeys and 2FA are a gap against the portal's current security screens and are tracked as a post-demo decision.
- ADR 0011's "points stays empty" is superseded for the demo scale only.
