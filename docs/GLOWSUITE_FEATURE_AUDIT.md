# GlowSuite Feature Audit

**Product:** GlowSuite — cloud-based Salon & Spa Management System  
**Audit date:** August 11, 2026  
**Codebase:** `saloon-management` (Laravel 12 API + React 19 SPA)

This document captures:

1. Overall alignment vs the product vision  
2. What is integrated today (fully or partially)  
3. The complete master backlog (48 items) with verified integration status  
4. A concise list of **not integrated** items only  

---

## Executive summary

GlowSuite has a **strong multi-tenant SaaS foundation**. Core salon workflows for **appointments**, **staff/RBAC**, **catalog**, **multi-branch ops**, and **platform administration** are real and production-oriented.

The largest gaps vs a full “Salon & Spa Management System” pitch are:

| Pillar | Verdict |
|--------|---------|
| Appointments | **Strong** — core workflow integrated |
| Staff management | **Strong for HR/RBAC** — weak for scheduling, payroll, performance |
| CRM | **Strong for core CRM** — full CRUD, tags, visit history; loyalty/campaigns remain future |
| Inventory | **Not integrated** — mock UI only |
| Billing | **Split** — SaaS subscription billing is strong; in-salon payment capture integrated |
| Analytics / operations | **Strong** — live reports + analytics summary API; sidebar exposed |

**Counts (master backlog #1–48):**

| Status | Count | Meaning |
|--------|-------|---------|
| ⚠️ Partial | 8 | Some API/UI exists; not end-to-end |
| ❌ Not integrated | 34 | No real implementation |
| ✅ Backlog item complete | 6 | CRM (#2), payment (#3), reports (#4), POS (#5), visit history (#12), tags (#13) |

**Separate from the backlog:** 15 major product areas are fully integrated today (see [What is integrated today](#what-is-integrated-today)).

---

## Overall alignment

### Product vision pillars

| Pillar | Integrated now | Gap |
|--------|----------------|-----|
| **Appointments** | Calendar, booking, walk-in/POS, reminders, receipts | Online client booking, waitlist |
| **Billing** | SaaS plans (Razorpay/Stripe), subscription lock, referrals, appointment payment capture, POS checkout | Tax invoicing, payment terminals |
| **Inventory** | Route + permissions + mock UI | Backend, stock, POs, suppliers |
| **CRM** | Full CRUD UI, tags/segments, visit history, appointment links | Loyalty, campaigns, public portal |
| **Staff** | CRUD, RBAC, weekly schedule on profile | Roster, attendance, commission calc, payroll |
| **Operations** | Settings, branches, dashboards, platform admin | Expenses, audit log, help center, i18n |

### Subscription modules (plan gating)

Defined in `app/Support/Subscription/SubscriptionModules.php` and seeded in `database/seeders/SubscriptionPlanSeeder.php`:

| Module | Free | Basic | Pro / Enterprise |
|--------|:----:|:-----:|:----------------:|
| Appointments | ✅ | ✅ | ✅ |
| Customers | ✅ | ✅ | ✅ |
| Staff | ✅ | ✅ | ✅ |
| Catalog | ✅ | ✅ | ✅ |
| Roles | ✅ | ❌ | ✅ |
| Settings / Branches | ✅ | ✅ | ✅ |
| Billing (SaaS) | ❌ | ✅ | ✅ |
| Inventory | ❌ | ✅* | ✅ |
| Analytics | ❌ | ❌ | ✅ |

\*Inventory is gated on Basic+ plans but **has no backend implementation yet**.

---

## What is integrated today

### ✅ Fully integrated

| Area | Evidence |
|------|----------|
| **Appointments (CRUD)** | `AppointmentController`, calendar UI, `BookingModal`, status workflow |
| **Walk-in / floor POS** | `/pos` cart checkout, multi-item sales, payment, stock deduct | Terminal integration |
| **Appointment notifications (email)** | `AppointmentNotificationService`, confirmation/cancel/staff assignment |
| **Appointment reminders (scheduled)** | `appointments:send-reminders` hourly in `bootstrap/app.php` |
| **PDF receipts** | `AppointmentReceiptController`, `InvoiceReceiptService` |
| **Salon payment capture** | `AppointmentPaymentService`, capture/refund API, booking + POS + detail UI |
| **Reports & analytics** | `SalonReportsView`, `GET /v1/analytics/summary`, Payments tab, sidebar nav |
| **Staff CRUD + RBAC** | `StaffController`, `RoleController`, assign-permissions |
| **Staff assignable roles (Basic plan)** | `GET /v1/staff/assignable-roles` |
| **Catalog & services** | Categories, services, products, tenant catalog offerings |
| **Multi-branch** | Tenant + platform branch management, branch-scoped data |
| **SaaS subscription billing** | Plans, Razorpay/Stripe checkout, webhooks, upgrade flow, read-only lock |
| **Salon referral program** | `SalonReferralController`, credits dashboard |
| **Affiliate partner portal** | Dashboard, referrals, commissions, withdrawals |
| **Platform admin** | Onboarding hub, subscription management, platform settings, reports |
| **Auth & onboarding** | Register, login, OTP password reset, onboarding wizard |
| **Tenant dynamic config** | Business profile, salon configuration, regional formatting, custom SMTP |
| **Owner dashboard (live data)** | `SalonOwnerDashboardView` aggregates appointments/customers |
| **Customers / CRM** | Full CRUD API + UI; tags/segments; visit history; deep links from appointments/reports |

### ⚠️ Partially integrated

| Area | What exists | What's missing |
|------|-------------|----------------|
| **Inventory** | Branch stock, movements, suppliers, POs, `InventoryView` API | Advanced forecasting, barcode scanning |
| **Staff scheduling** | `weekly_schedule` JSON on staff profile | Branch roster, shift management, availability blocking |
| **Staff commission / salary** | DB fields + API accept `commission_rate`, `per_month_salary` | No UI in `StaffView`; no auto-calculation from appointments |
| **Staff performance** | Dashboard widgets derive some stats from appointments | `PerformanceView` is mock; ratings/attendance fake |
| **SMS notifications** | `TenantNotificationDispatcher::sendSms` wired for reminders | Production hardening, delivery logs, template editor |
| **Promo / discounts** | Per-appointment discount in booking modal | Global promo codes, usage limits |
| **PWA** | Service worker + install prompt | Static manifest (not tenant-branded) |
| **2FA** | `TwoFactorView` UI + login redirect hook | Backend TOTP; mock path via `VITE_USE_MOCK_AUTH` |
| **WhatsApp** | WhatsApp number on business profile | WhatsApp Business API / messaging |

### ❌ Mock / placeholder only

| File / area | Notes |
|-------------|-------|
| `resources/js/views/AnalyticsView.jsx` | `MOCK_DATA`; route redirects to `/reports` |
| `resources/js/views/staff/PerformanceView.jsx` | Simulated KPIs, ratings, attendance |
| `resources/js/views/auth/TwoFactorView.jsx` | Mock verification when `VITE_USE_MOCK_AUTH=true` |

### Hidden but built

| Route | Status |
|-------|--------|
| `/reports` | Real data (`SalonReportsView`); hidden in `AppSidebar` `HIDDEN_SIDEBAR_PATHS` |
| `/analytics` | Redirects to `/reports` |

---

## Master backlog — full list with integration status

Legend: **✅ Integrated** · **⚠️ Partial** · **❌ Not integrated**

Effort guide: **S** ≈ 1–3 days · **M** ≈ 1–2 weeks · **L** ≈ 2–4 weeks · **XL** ≈ multi-sprint

---

### Phase 0 — Foundation gaps

| # | Module | Status | What exists | What's missing | Effort |
|---|--------|--------|-------------|----------------|--------|
| 1 | Inventory (end-to-end) | ✅ | Stock API, movements, suppliers, POs, `InventoryView`, POS deduct | Forecasting, barcode scanning | — |
| 2 | Customer CRM UI | ✅ | Full CRUD UI, notes editor, tags, visit history | Loyalty, campaigns | — |
| 3 | Salon payment capture | ✅ | Capture/refund API, booking modal, POS, detail panel, receipt PDF | Terminal integration, credit notes | — |
| 4 | Reports & analytics (real) | ✅ | `SalonReportsView`, analytics summary API, Payments tab, nav exposed | `/analytics` redirects to reports; advanced BI | — |
| 5 | Full retail POS | ✅ | Cart checkout UI, `/v1/pos/catalog`, `/v1/pos/checkout`, stock deduct | Terminal integration | — |

---

### Phase 1 — Staff operations

| # | Module | Status | What exists | What's missing | Effort |
|---|--------|--------|-------------|----------------|--------|
| 6 | Staff scheduling / roster | ⚠️ | `weekly_schedule` on profile | Branch roster, shifts, booking availability | L |
| 7 | Attendance & leave | ❌ | Tabs commented out in `StaffProfileView` | Clock in/out, leave requests, approvals | L |
| 8 | Staff commissions | ⚠️ | DB + API fields | UI, rules engine, calc from appointments | M |
| 9 | Staff performance (real) | ⚠️ | Dashboard stats from appointments | Replace mock `PerformanceView`; targets | M |
| 10 | Staff payroll | ❌ | `per_month_salary` field only | Pay periods, payslips, roll-up | L |

---

### Phase 2 — Customer growth & retention

| # | Module | Status | What exists | What's missing | Effort |
|---|--------|--------|-------------|----------------|--------|
| 11 | Online booking portal | ❌ | Internal `bookingOptions` API | Public page/widget, guest booking | XL |
| 12 | Customer visit history | ✅ | Timeline on customer profile + stats | Export, email to customer | S |
| 13 | Customer segments & tags | ✅ | Salon tags, filters, segment presets | Auto-segment rules, campaigns | M |
| 14 | Loyalty program | ❌ | Dashboard icon keys only | Points, tiers, redemption | L |
| 15 | Packages & memberships | ❌ | — | Prepaid bundles, balances, expiry | L |
| 16 | Gift cards | ❌ | — | Issue, redeem, balance | M |
| 17 | Promo codes & discounts | ⚠️ | Per-appointment discount | Global codes, limits | M |

---

### Phase 3 — Communications

| # | Module | Status | What exists | What's missing | Effort |
|---|--------|--------|-------------|----------------|--------|
| 18 | SMS appointment reminders | ⚠️ | SMS in `AppointmentNotificationService` | Delivery logs, retry, template editor | M |
| 19 | WhatsApp notifications | ❌ | WhatsApp on business profile | WhatsApp Business API | L |
| 20 | Marketing campaigns | ❌ | — | Broadcast SMS/email/WhatsApp | L |
| 21 | Marketing automation | ❌ | — | Triggers, drip sequences | XL |
| 22 | Notification template manager | ❌ | Hardcoded mailables | Editable per-salon templates | M |

---

### Phase 4 — Spa-specific & advanced ops

| # | Module | Status | What exists | What's missing | Effort |
|---|--------|--------|-------------|----------------|--------|
| 23 | Resource / room management | ❌ | — | Rooms, beds, equipment booking | L |
| 24 | Service packages (spa combos) | ❌ | Single services in catalog | Multi-service packages | M |
| 25 | Waitlist & queue | ❌ | Today's walk-in list in POS | Queue position, SMS when ready | M |
| 26 | Expense tracking | ❌ | — | Operating expenses, categories | M |
| 27 | Vendor / supplier management | ✅ | Supplier CRUD API + inventory UI | Vendor performance, payment terms | S |
| 28 | Purchase orders | ✅ | PO create/place/receive, stock in | Partial receives, approval workflow | S |

---

### Phase 5 — Finance & compliance

| # | Module | Status | What exists | What's missing | Effort |
|---|--------|--------|-------------|----------------|--------|
| 29 | Advanced invoicing & tax | ⚠️ | PDF receipt, invoice number | Line items, GST/tax, credit notes | L |
| 30 | Daily sales reconciliation | ❌ | Appointment totals | Cash drawer, method breakdown | M |
| 31 | Accounting export | ❌ | — | Tally/QuickBooks/Xero CSV | M |
| 32 | Corporate / B2B billing | ❌ | — | Monthly consolidated invoices | L |

---

### Phase 6 — Customer-facing & reputation

| # | Module | Status | What exists | What's missing | Effort |
|---|--------|--------|-------------|----------------|--------|
| 33 | Customer reviews & ratings | ❌ | Mock ratings in `PerformanceView` | Post-visit rating, storage, averages | M |
| 34 | Customer mobile / portal app | ❌ | — | Booking, history, loyalty PWA/app | XL |
| 35 | Refer-a-friend (customer) | ❌ | Salon-to-salon referral exists | Customer refers friends for salon credit | M |

---

### Phase 7 — Platform, security & polish

| # | Module | Status | What exists | What's missing | Effort |
|---|--------|--------|-------------|----------------|--------|
| 36 | Native 2FA (TOTP) | ❌ | Mock `TwoFactorView` | Backend enrollment, verify, recovery | M |
| 37 | Dynamic PWA manifest | ⚠️ | Static `public/manifest.json` | Per-tenant name/colors/icons | S |
| 38 | In-app help / knowledge base | ❌ | Help icon in sidebar | Docs, FAQs, support | M |
| 39 | Audit log | ❌ | — | Change history for settings/data | M |
| 40 | Data export / GDPR tools | ❌ | — | Customer export, deletion requests | M |
| 41 | Multi-language (i18n) | ❌ | English UI; regional formatting | Translation files, locale switcher | L |
| 42 | API webhooks (outbound) | ⚠️ | Payment webhooks (inbound) | Outbound events for integrations | M |

---

### Phase 8 — Hardware & AI (optional)

| # | Module | Status | Effort |
|---|--------|--------|--------|
| 43 | Receipt / thermal printer integration | ❌ | M |
| 44 | Barcode / SKU scanner | ❌ | S |
| 45 | Payment terminal (Razorpay POS, etc.) | ❌ | L |
| 46 | Demand forecasting | ❌ | L |
| 47 | AI staff utilization insights | ❌ | L |
| 48 | AI rebooking / churn alerts | ❌ | M |

---

## Not integrated — summary list

Items with status **❌ Not integrated** (34 items). Safe to treat these as future work.

1. Inventory (end-to-end)  
2. Attendance & leave  
3. Staff payroll  
4. Online booking portal  
5. Loyalty program  
6. Packages & memberships  
7. Gift cards  
8. WhatsApp notifications  
9. Marketing campaigns  
10. Marketing automation  
11. Notification template manager  
12. Resource / room management  
13. Service packages (spa combos)  
14. Waitlist & queue  
15. Expense tracking  
16. Vendor / supplier management  
17. Purchase orders  
18. Daily sales reconciliation  
19. Accounting export  
20. Corporate / B2B billing  
21. Customer reviews & ratings  
22. Customer mobile / portal app  
23. Refer-a-friend (customer)  
24. Native 2FA (TOTP)  
25. In-app help / knowledge base  
26. Audit log  
27. Data export / GDPR tools  
28. Multi-language (i18n)  
29. Receipt / thermal printer integration  
30. Barcode / SKU scanner  
31. Payment terminal integration  
32. Demand forecasting  
33. AI staff utilization insights  
34. AI rebooking / churn alerts  

> **Note:** Partial items (#5, #6, #8, #9, #17, #18, #29, #37, #42) are listed separately below — they have some code but are **not complete**.

### Partial — not complete (8 items)

These have scaffolding or a subset of functionality but are **not fully integrated**:

1. Staff scheduling / roster (#6)  
2. Staff commissions (#8)  
3. Staff performance (#9)  
4. Promo codes & discounts (#17)  
5. SMS appointment reminders (#18)  
6. Advanced invoicing & tax (#29)  
7. Dynamic PWA manifest (#37)  
8. API webhooks outbound (#42)  

---

## Recommended build order

If integrating the backlog sequentially:

```
Phase 0:  1 → 2 → 3 → 4 → 5
Phase 1:  6 → 7 → 8 → 9 → 10
Phase 2:  12 → 13 → 14 → 15 → 16 → 17
Phase 3:  18 → 22 → 19 → 20 → 21
Phase 4:  27 → 28 → 26 → 23 → 24 → 25
Phase 5:  30 → 29 → 31 → 32
Phase 6:  11 → 33 → 35 → 34
Phase 7:  36 → 37 → 39 → 40 → 38 → 41 → 42
Phase 8:  43 → 44 → 45 → 46 → 47 → 48
```

Rough estimate: **6–12 months** for one developer, depending on depth and testing.

---

## Architecture reference

### Settings resolution order

`salon_settings` → `platform_settings` → `config/tenant_settings.php`

### Bootstrap flow

`main.jsx` → `hydratePlatformBranding()` + `hydrateModuleCatalog()`

### Scheduled tasks

| Command | Schedule | Purpose |
|---------|----------|---------|
| `subscriptions:expire` | Daily 00:15 | Mark expired subscriptions |
| `subscriptions:send-renewal-reminders` | Daily 09:00 | Email salon owners before renewal |
| `appointments:send-reminders` | Hourly | Customer appointment reminders |

### Key files

| Area | Path |
|------|------|
| Subscription modules | `app/Support/Subscription/SubscriptionModules.php` |
| API routes | `routes/api.php` |
| Navigation | `resources/js/lib/navigation.js` |
| Hidden sidebar paths | `resources/js/components/layout/AppSidebar.jsx` |
| Mock inventory | — (replaced by real `InventoryView`) |
| Mock analytics | `resources/js/views/AnalyticsView.jsx` |
| Real reports | `resources/js/views/SalonReportsView.jsx` |
| Mock staff performance | `resources/js/views/staff/PerformanceView.jsx` |
| Tenant formatting | `resources/js/lib/tenantFormatting.js` |
| Plan seeder | `database/seeders/SubscriptionPlanSeeder.php` |

---

## How to use this document

- Pick an item by **#** from the master backlog and say e.g. `Start #1` to scope implementation.  
- Treat **❌ Not integrated** items as confirmed gaps — no surprise dependencies.  
- Treat **⚠️ Partial** items as “extend, don’t restart” — API or UI may already exist.  
- Re-run this audit after major releases by checking the key files above.

---

*Generated from codebase inspection on August 11, 2026. Status reflects `saloon-management` working tree at audit time.*
