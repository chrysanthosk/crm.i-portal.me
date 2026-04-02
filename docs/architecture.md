# CRM Architecture Overview

## Purpose

This document explains the architectural shape of `crm.i-portal.me`, the main modules in the application, and how responsibilities are currently divided.

## System shape

The application is currently a **modular Laravel monolith**.

Core characteristics:
- Laravel 12 application
- Blade/Tailwind/Alpine style frontend approach
- server-rendered module pages
- role/permission-gated modules
- DB-backed settings and operational metadata
- admin/settings functionality living alongside business modules in one deployable unit

This is a reasonable shape for the current scope, but it requires strong documentation and boundary discipline as the feature set grows.

## Architectural layers

### 1. Access and identity
Handles:
- login / logout
- password reset
- profile management
- 2FA and trusted devices
- roles and permissions

Primary files:
- `routes/auth.php`
- `app/Http/Controllers/ProfileController.php`
- `app/Http/Controllers/TwoFactorController.php`
- `app/Models/User.php`
- `app/Models/Role.php`
- `app/Models/Permission.php`

### 2. Core business modules
Handles:
- clients
- appointments
- services
- products
- suppliers
- inventory
- staff
- POS / sales flows

Primary route areas:
- `/clients`
- `/appointments`
- `/services`
- `/products`
- `/suppliers`
- `/inventory`
- `/staff`
- `/pos`

### 3. Reporting and finance
Handles:
- operational reports
- analytics
- BI/reporting views
- financial views (income / expenses)
- Z reports

Primary route areas:
- `/reports`
- `/reports/financial/*`

### 4. Settings and administration
Handles:
- users
- roles / permissions
- SMTP
- configuration
- SMS settings and logs
- payment methods
- loyalty settings
- GDPR tooling
- audit log

Primary route area:
- `/settings/*`

## Module boundaries

Recommended module boundary model:

- Auth & Identity
- CRM Core Data (clients, staff, suppliers)
- Scheduling (appointments, calendar)
- Catalog (services, products, categories, VAT)
- Commerce / POS
- Communications (SMTP, SMS, bulk messaging)
- Reporting & Finance
- Administration & Compliance

This documentation model should be used consistently when discussing roadmap or refactoring work.

## Permission model

The application relies on explicit permission keys seeded in `PermissionsSeeder`.

Examples:
- `client.manage`
- `appointment.manage`
- `services.manage`
- `products.manage`
- `cashier.manage`
- `reports.view`
- `settings.smtp`
- `sms.manage`
- `gdpr.manage`

This means functional access is intentionally segmented even though the application is a monolith.

## Architectural risks

Current architectural risks include:
- settings/admin complexity growing inside the same deployable unit as daily operations
- communications/integration settings accumulating without a stronger service abstraction model
- route/controller sprawl as more modules are added
- product scope expansion without clearer workflow prioritization

## Recommendation

Keep the application as a modular monolith for now, but document modules and workflows rigorously. Only consider deeper separation later if operational scale or integration complexity justifies it.
