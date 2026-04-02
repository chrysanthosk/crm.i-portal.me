# Privileged Flow Matrix

## Purpose

Map privileged or security-sensitive flows to recommended test depth.

| Area | Flow | Risk level | Smoke | Regression | Manual release check |
| --- | --- | --- | --- | --- | --- |
| Auth | Login / logout | High | Yes | Yes | Yes |
| Auth | Password reset | High | Yes | Yes | Yes |
| Auth | 2FA enable / confirm / disable | High | Basic | Yes | Yes |
| Auth | Trusted device behavior | High | No | Yes | Yes |
| Profile | Password change | High | Yes | Yes | Yes |
| Profile | Email change / confirm | High | Basic | Yes | Yes |
| Admin | User CRUD | High | Basic | Yes | Yes |
| Admin | Role / permission updates | High | Basic | Yes | Yes |
| Settings | SMTP config + test | High | Basic | Yes | Yes |
| Settings | SMS provider config | High | Basic | Yes | Yes |
| SMS | Bulk SMS send | High | No | Yes | Yes |
| POS | Checkout | High | Basic | Yes | Yes |
| POS | Void sale | High | No | Yes | Yes |
| Reports | Report access gating | Medium | Basic | Yes | Optional |
| GDPR | Purge client data | High | No | Yes | Yes |
| Audit | Audit log visibility | Medium | Basic | Yes | Optional |

## Interpretation

- **Smoke**: quick confidence that the route/flow still works at all
- **Regression**: deeper behavior/authorization verification
- **Manual release check**: human verification recommended before important releases until automation is stronger
