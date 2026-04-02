# 2FA and Trusted-Device Regression Notes

## Purpose

Document the first implementation slice of CK-16.

## What was added

A focused feature test file was added for high-value 2FA state and guard behavior.

Covered cases:
- enabling 2FA generates a pending secret in session
- confirming 2FA without a pending secret fails safely
- disabling 2FA requires the correct current password
- regenerating recovery codes requires 2FA to already be enabled

## Why this matters

These tests protect the security-sensitive setup/disable flows even before deeper TOTP/trusted-device simulations are added.

## Recommended next expansion

Future CK-16 coverage should extend into:
- successful 2FA confirm path with deterministic valid code handling
- recovery-code consumption during challenge flow
- trusted-device cookie bypass behavior
- challenge expiration behavior
- remember-device cookie creation behavior
