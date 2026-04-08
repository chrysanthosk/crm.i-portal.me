# SMTP and SMS Regression Notes

## Purpose

Document the first implementation slice of CK-17.

## What was added

A focused feature test file was added for high-value SMTP and SMS settings behavior.

Covered cases:
- SMTP settings can be saved by an admin
- SMTP password is stored encrypted rather than plaintext
- SMTP test path fails safely when SMTP is disabled/unconfigured
- SMS provider settings can be saved
- SMS provider active state can be toggled
- SMS provider priority order can be updated

## Why this matters

These flows are operationally sensitive because they affect outbound communications and configuration correctness.

## Recommended next expansion

Future CK-17 coverage should extend into:
- successful SMTP test mail path
- SMS test-send behavior with service mocking
- provider deletion behavior
- provider fetch-settings response behavior
- authorization-boundary checks for non-admin/non-sms-manage users
