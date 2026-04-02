# QA Test Plan

## Purpose

Define the initial QA baseline for `crm.i-portal.me`, with emphasis on smoke coverage, regression coverage, and privileged-risk flows.

## Testing goals

This QA plan is intended to:
- validate that critical business and security flows still work after change
- catch regressions in privileged/admin-only paths
- create a repeatable release-confidence checklist
- support future automation without relying on memory-based testing

## Coverage model

### 1. Smoke coverage
Smoke coverage answers:
- does the core application still function at all?

Smoke tests should be fast and focused on business-critical entry points.

### 2. Regression coverage
Regression coverage answers:
- do previously working behaviors still behave correctly after change?

Regression tests should cover privileged and high-risk flows where breakage is costly.

## Highest-risk areas

Priority areas for testing:
1. authentication and logout
2. password reset
3. profile updates and password changes
4. 2FA / trusted device flows
5. user / role / permission management
6. SMTP and SMS settings
7. bulk SMS sending
8. POS checkout / void behavior
9. reports and finance access controls
10. GDPR purge and other destructive actions
11. audit visibility for sensitive actions

## Smoke test suite

Recommended smoke suite for every meaningful release:

### Access and auth
- login page loads
- valid login succeeds
- invalid login fails safely
- logout succeeds
- forgot password page loads
- reset-password flow can be initiated

### User profile and security
- profile page loads for authenticated user
- profile update works
- password change works with correct current password
- password change fails with wrong current password
- 2FA settings page loads

### Business-critical modules
- dashboard loads
- clients index loads for authorized user
- appointments index loads for authorized user
- products/services pages load for authorized user
- POS page loads for authorized user

### Admin/settings
- settings pages are reachable for an authorized admin
- SMTP settings page loads
- configuration page loads
- roles/users page loads
- audit page loads

## Regression coverage recommendations

### Authentication / identity
Regression tests should cover:
- login remember-me behavior
- trusted-device bypass behavior
- 2FA challenge enforcement for 2FA-enabled users
- password reset with valid token
- password reset failure with invalid token
- profile email-change flow

### Authorization boundaries
Regression tests should cover:
- unauthorized user blocked from `/settings/*`
- unauthorized user blocked from admin-only actions
- unauthorized user blocked from POS if missing `cashier.manage`
- unauthorized user blocked from reports requiring report permissions
- unauthorized user blocked from SMS/admin operations

### Sensitive settings
Regression tests should cover:
- SMTP settings save path
- configuration update path
- SMS provider save / toggle / priority changes
- payment method and loyalty settings updates

### Destructive / high-impact actions
Regression tests should cover:
- sale void action authorization and expected effect
- GDPR purge authorization and safety checks
- user/role destructive changes where implemented
- restore/deploy scripts via runbook-level operational validation outside PHPUnit

## Manual test matrix for privileged flows

These flows should be manually verified before important releases unless automated coverage exists:
- login / logout / reset-password
- 2FA enable / confirm / disable / recovery regeneration
- admin user CRUD
- role/permission assignment
- SMTP test send
- SMS provider configuration and test send
- bulk SMS send path
- POS checkout and void
- audit log visibility
- GDPR purge path

## Release confidence checklist

Before release:
- CI green
- secret scanning green
- smoke suite passes
- no known privilege regression in admin/settings flows
- no known breakage in POS / scheduling / client flows relevant to release
- runbooks remain valid for deployment/backup/restore if ops-affecting changes are included

## Recommendations for future automation

Recommended next automation targets:
1. permission-boundary tests for admin/settings routes
2. 2FA and trusted-device tests
3. SMTP/SMS settings save-path tests
4. POS checkout / void regression tests
5. GDPR purge safety/authorization tests

## Notes

Not every risk belongs in PHPUnit only. Operational runbooks, backup/restore validation, and deployment verification should continue to exist as operator checks alongside application tests.
