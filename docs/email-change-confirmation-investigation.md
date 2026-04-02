# Email Change Confirmation Investigation

## Context

CK-10 surfaced unresolved behavior around the email change confirmation flow.

The controller implementation in `ProfileController::confirmEmailChange()` indicates that:
- a valid token should locate the user by `pending_email_token`
- the pending email should replace the current email
- pending fields should be cleared
- the user should be redirected to login

However, the earlier regression test repeatedly observed that the user email remained unchanged in the test scenario.

## Current status

This issue has been broken out into Jira as:
- `CK-14` — Investigate email change confirmation behavior

## Investigation goals

1. Confirm whether the controller behaves correctly in all intended scenarios.
2. Confirm whether the prior failing test fixture missed an application prerequisite.
3. Lock in the intended behavior via dedicated tests.
4. If the application is wrong, fix the flow and keep the regression tests.

## Dedicated regression coverage added

A dedicated test file now exists:
- `tests/Feature/ProfileEmailChangeTest.php`

Coverage includes:
- successful confirmation updates email and clears pending fields
- invalid token is rejected
- expired token is rejected and clears pending fields
- duplicate target email is rejected safely

## Recommendation

Treat this as an application behavior investigation, not as a reason to weaken the overall CI baseline. The correct outcome is to resolve the flow and keep the regression coverage.
