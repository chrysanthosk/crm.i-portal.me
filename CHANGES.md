# Security & Quality Hardening — Changes Log

All changes made during the security and quality review of the CRM project.

---

## Critical Security Fixes

### 1. SMS API Key Encryption
**File:** `app/Models/SmsSetting.php`

Added Laravel `encrypted` cast to `api_key` and `api_secret` fields. Provider credentials are now stored encrypted at rest using the app key, not in plaintext.

### 2. Rate Limiting on Sensitive Endpoints
**Files:** `routes/auth.php`, `routes/web.php`

Added `throttle` middleware to prevent brute-force and resource-exhaustion attacks:

| Endpoint | Limit |
|----------|-------|
| `POST /login` | 5 req/min |
| `POST /two-factor-challenge` | 5 req/min |
| `POST /forgot-password` | 5 req/min |
| `POST /reset-password` | 5 req/min |
| `POST /bulk-sms/send` | 10 req/min |
| All CSV import endpoints | 20 req/min |

### 3. Session Encryption Enabled
**Files:** `.env.example`, `.env.docker.example`

Changed `SESSION_ENCRYPT` from `false` to `true` in both env example files. Session data is now stored encrypted in the database.

### 4. Admin Seeder Credentials via Environment Variable
**File:** `database/seeders/InitialSetupSeeder.php`

Removed hardcoded `admin@example.com` / `ChangeMe123!!` credentials. The seeder now reads `ADMIN_EMAIL` and `ADMIN_PASSWORD` from environment variables. It throws a `RuntimeException` if `ADMIN_PASSWORD` is not set, preventing accidental deployment with a known password.

**Action required:** Add to your `.env` / `.env.docker` before running the seeder:
```
ADMIN_EMAIL=your@email.com
ADMIN_PASSWORD=your-strong-password
```

Both `.env.example` and `.env.docker.example` have been updated with these new variables.

---

## Database Integrity

### 5. Foreign Key: `sales.voided_by → users`
**File:** `database/migrations/2026_04_08_000001_add_voided_by_foreign_key_to_sales_table.php`

Added the previously commented-out FK constraint between `sales.voided_by` and `users.id` (nullOnDelete). Skips automatically on SQLite (used in tests).

### 6. Composite Indexes on Appointments
**File:** `database/migrations/2026_04_08_000002_add_composite_indexes_to_appointments_table.php`

Added two composite indexes to cover the most common appointment queries:

| Index | Columns |
|-------|---------|
| `idx_appointments_client_start` | `(client_id, start_at)` |
| `idx_appointments_staff_start` | `(staff_id, start_at)` |

---

## Input Validation

### 7. Notes & Comments Length Limits
**Files:** `app/Http/Requests/ClientRequest.php`, `app/Http/Controllers/ClientController.php`

Added `max:5000` to `notes` and `comments` fields in both the new `ClientRequest` FormRequest and the inline CSV import validator. Appointment `notes` and `internal_notes` capped at `max:2000` in `AppointmentRequest`.

### 8. Date Range Cap in Reports
**File:** `app/Http/Controllers/Reports/ReportsController.php`

Added a private `clampRange(string $from, string $to, int $maxDays = 366): array` helper to prevent unbounded date range queries. Applied in:

- `analytics()` — capped at 366 days
- `loadReportData()` — capped at 366 days (covers staff performance, PDF reports)
- `zReportGenerate()` — capped at 31 days (Z-reports are shift/day level)

---

## Code Quality — FormRequests

### 9. `ClientRequest` FormRequest
**File:** `app/Http/Requests/ClientRequest.php` *(new)*

Extracted inline validation from `ClientController::validateClient()` into a dedicated `FormRequest`. The private helper method has been removed from the controller. Used in `store()` and `update()`.

### 10. `AppointmentRequest` FormRequest
**File:** `app/Http/Requests/AppointmentRequest.php` *(new)*

Extracted inline validation from `AppointmentController::validateAppointment()` into a dedicated `FormRequest`. The cross-field service/category check is preserved via `withValidator()`. The private helper and the `Validator` facade import have been removed from the controller. Used in `store()` and `update()`.

---

## Configuration Notes

### 11. Redis Recommendation for Production
**Files:** `.env.example`, `.env.docker.example`

Added comments to `QUEUE_CONNECTION` and `CACHE_STORE` advising Redis for production use. The default remains `database` for local/dev environments.

### 12. APP_DEBUG Production Warning
**File:** `.env.docker.example`

Added a warning comment above `APP_DEBUG=true` reminding developers to set it to `false` in production to avoid exposing stack traces.

---

## Summary

| Category | Items Fixed |
|----------|------------|
| Critical Security | 4 (encryption, rate limiting, session, seeder credentials) |
| Database Integrity | 2 (FK constraint, composite indexes) |
| Input Validation | 2 (length limits, date range cap) |
| Code Quality | 2 (FormRequests for Client and Appointment) |
| Configuration | 2 (Redis hints, APP_DEBUG warning) |
