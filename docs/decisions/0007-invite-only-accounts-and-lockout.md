# 0007 - Invite-only accounts, password policy and login lockout

Date: 2026-11-18 | Status: Accepted; password policy and token lifetimes to be confirmed | Decided by: Iddriss (invite-only assumed from the brief)

## Context

Accounts belong to students and college staff, so anyone being able to register would be wrong. The brief also asks for login rate limiting, lockout after repeated failures, a password policy and expiring reset links.

## Decision

**Accounts**
- There is no public registration. An administrator invites a person by email; they receive a single-use link valid for **72 hours**, set their own password, and the account becomes active. Opening the link also verifies the email, so there is no separate verification step.
- Only a hash of the invitation token is stored. Re-inviting replaces the old link.

**Passwords**
- At least 10 characters with upper and lower case letters and a digit. In production the password must also not appear in known data breaches.
- Reset links last **60 minutes** and work once. Resetting or changing a password signs the user out on every other device.
- "Forgot password" always answers the same way, so it cannot be used to find out who has an account, and invited or disabled accounts receive nothing.

**Login lockout**
- 5 failed attempts for the same email lock that email for **15 minutes** (HTTP 429 with `Retry-After`). The lock is keyed on the email text, not on the account, so existing and non-existing emails behave identically. A successful login clears the count.
- Public sign-in endpoints are also limited to 20 requests per minute per IP.
- A hash check always runs, even for unknown emails, so response time does not reveal whether an email has an account.

## Alternatives considered

- **Lock only existing accounts:** reveals which emails exist.
- **Lock by IP only:** an attacker spreading over many IPs would not be slowed down.

## Consequences

- Someone who types a colleague's email five times can lock that colleague out for 15 minutes. Accepted as the cost of not revealing accounts.
- **To confirm with the college:** the password rule, the 12 hour token lifetime, and the invitation and reset lifetimes.
