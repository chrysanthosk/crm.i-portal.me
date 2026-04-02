# CRM Scope and Workflows

## Purpose

This document clarifies what the CRM currently appears to cover, who it serves, and what the main workflows are.

## Intended scope

Based on the implemented modules, this system is broader than a contact list or admin panel. It appears to support operations for a service-oriented business with CRM, scheduling, POS, inventory, communications, and reporting needs.

## Likely user personas

### 1. Administrator
Needs to:
- manage users and roles
- configure SMTP / system / SMS settings
- review audits and compliance tooling
- manage catalog/settings structures

### 2. Front-desk / operations staff
Needs to:
- manage clients
- manage appointments
- view calendar
- interact with suppliers or inventory as needed

### 3. Cashier / sales operator
Needs to:
- use POS
- complete checkout
- print receipts
- view/void sales when authorized

### 4. Manager / owner
Needs to:
- review analytics and reports
- view finance data
- review staff performance
- oversee settings and operational health

## Current capability areas

Current system capability areas include:
- authentication and security
- profile and email-change management
- 2FA / trusted devices
- clients
- appointments and calendar
- services and products
- suppliers and inventory
- POS and sales
- reports and financial views
- SMTP and SMS settings
- loyalty and payment methods
- GDPR purge tooling
- audit logging

## Main workflows

### Client management workflow
- create / import client
- update client information
- export client data
- use client record during appointments or sales

### Appointment workflow
- create appointment
- assign resources/services
- move/update appointment
- review appointment list/calendar
- export appointment data

### Product/service workflow
- maintain service catalog
- maintain product catalog
- import/export catalog data
- use in sales or appointment contexts

### POS workflow
- open POS
- perform checkout
- issue receipt
- review sales
- void sales when allowed

### Communications workflow
- configure SMTP
- configure SMS providers/settings
- send bulk SMS when authorized
- review SMS logs

### Reporting workflow
- access operational reports
- review analytics and BI views
- view financial data
- generate/manage Z reports

## Non-goals to clarify explicitly

The repo does not yet clearly document boundaries such as:
- whether this is single-location only or multi-location ready
- whether finance is lightweight operational reporting or full accounting
- whether CRM scope includes pipeline/opportunity/sales CRM behavior beyond POS
- whether communications are transactional only or campaign-oriented

These should be clarified in product documentation.

## Recommendations

- define what the CRM is primarily for in one sentence
- document primary users and their top 3 workflows
- separate current implemented scope from desired roadmap scope
- keep Confluence as the narrative explanation layer and Jira as the execution layer
