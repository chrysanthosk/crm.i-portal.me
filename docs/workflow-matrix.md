# Workflow Matrix

## Purpose

Provide a quick map between user roles, modules, and typical actions.

| Area | Typical user | Main actions |
| --- | --- | --- |
| Auth / Profile | All users | Login, logout, password reset, profile update, 2FA |
| Clients | Operations staff | Create, edit, import, export client records |
| Appointments | Operations staff | Schedule, edit, move, export appointments |
| Services | Admin / operations | Maintain service catalog |
| Products | Admin / operations | Maintain product catalog |
| Suppliers | Admin / operations | Manage supplier records, import/export |
| Inventory | Operations / admin | Review and update inventory state |
| POS | Cashier | Checkout, receipt, review/void sales |
| Reports | Manager / owner | Review analytics, BI, financial data |
| Settings | Administrator | SMTP, SMS, config, roles, permissions, audit |
| GDPR | Administrator | Purge client data under policy controls |

## Notes

This matrix is intentionally simplified. Detailed permission mapping still lives in seeded permission keys and application route middleware.
