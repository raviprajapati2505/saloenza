# Saloenza Feature Gap — Detailed Planning (All Points)

**As of:** 21 September 2026  
**Prepared for:** Dr. Anjum Arshad Benkankonda  
**Purpose:** Consolidated planning reference for all feature gaps and suggestions — change level, field-level impacts per role, and business logic. **No code.** Ready to convert to Word/DOC.

**Baseline product:** Laravel 12 + React SPA multi-tenant salon SaaS (Saloenza). Roles: Platform Super Admin, Affiliate Partner, Salon Franchise Owner, Franchise Manager, Branch Manager, Staff. Customers are CRM records (no login). Sources: strategy doc vs `docs/Saloenza-Marketing-Feature-List.md` (incl. Appendix C).

---

## How to read each section

Every feature section uses this structure:

1. **Summary** — what is missing and why it matters  
2. **Current state** — what Saloenza already has  
3. **Change level** — UI / API / DB / jobs / integrations / permissions / plan gating  
4. **Field & entity changes** — new/changed tables and fields  
5. **Role impact** — who can see, configure, act, and approve  
6. **Business logic** — rules, triggers, calculations, edge cases  
7. **Dependencies, phasing, open decisions**

---

## Contents

### A. Missing / Partial from Saloenza (strategy alignment)

| # | Section | Priority | Status |
|---|---------|----------|--------|
| 01 | [WhatsApp automation](#01--whatsapp-automation) | High | Missing |
| 02 | [AI insights dashboard (Salon Intelligence)](#02--ai-insights-dashboard-salon-intelligence) | High | Missing |
| 03 | [Smart / predictive inventory](#03--smart--predictive-inventory) | Medium | Partial |
| 04 | [Full commission engine](#04--full-commission-engine) | High | Partial |
| 05 | [Customer win-back / retention automation](#05--customer-win-back--retention-automation) | High | Missing |
| 06 | [Loyalty, memberships, packages, gift cards](#06--loyalty-memberships-packages--gift-cards) | Medium | Missing (Appendix C) |
| 07 | [Marketing automation on tags/segments](#07--marketing-automation-on-segmentation--tags) | Medium | Partial |
| 08 | [Branch benchmarking](#08--branch-benchmarking) | Medium | Partial |
| 09 | [Advanced payroll](#09--advanced-payroll) | Low | Missing (Appendix C) |
| 10 | [API / third-party integrations](#10--api--third-party-integrations) | Medium | Missing |
| 11 | [Free migration + onboarding guarantee positioning](#11--productized-free-migration--onboarding--training-guarantee) | Low | Partial |

## B. Additional suggestions (beyond both documents)

| # | Section | Priority |
|---|---------|----------|
| 12 | [Arabic / bilingual UI and receipts](#12--arabic--bilingual-ui-and-receipts) | High |
| 13 | [No-show protection (card-on-file / prepayment)](#13--no-show-protection-card-on-file--prepayment) | Medium |
| 14 | [Two-way WhatsApp concierge](#14--two-way-whatsapp-concierge) | Medium |
| 15 | [Waitlist auto-fill](#15--waitlist-auto-fill) | Medium |
| 16 | [Google / Instagram Book Now](#16--google--instagram-book-now-integration) | Low |
| 17 | [Review & reputation management](#17--review--reputation-management) | Low |
| 18 | [Customer lifetime value (CLV) scoring](#18--customer-lifetime-value-clv-scoring) | Medium |
| 19 | [Real shift & attendance management](#19--real-shift--attendance-management) | Low |
| 20 | [Dynamic / off-peak pricing](#20--dynamic--off-peak-pricing) | Low |

---

## Recommended build sequence (cross-doc)

1. **WhatsApp automation foundation** (01) — message templates, opt-in, send log, providers  
2. **Commission engine** (04) — unlocks Pro-tier sales credibility  
3. **Win-back detection** (05) + **CLV** (18) — feed automation  
4. **AI insights dashboard** (02) — uses appointment/customer/inventory data already present  
5. **Marketing automation** (07) — orchestrates tags + WhatsApp/SMS/email  
6. **Smart inventory** (03) — extends existing PO flow  
7. **Branch benchmarking** (08) — reporting layer on existing multi-branch data  
8. Market must-haves for Qatar: **Arabic UI** (12), then **no-show protection** (13), **two-way WA** (14), **waitlist** (15)  
9. Decide go/no-go: loyalty (06), payroll (09), public API (10), positioning (11)  
10. Later: Book Now (16), reviews (17), attendance (19), dynamic pricing (20)

---

## Role cheat-sheet (used across all docs)

| Role code | Who |
|-----------|-----|
| `platform.super_admin` | Saloenza platform team |
| `affiliate.partner` | Referral partners (rarely touched by these features) |
| `salon.franchise_owner` | Full salon control |
| `salon.franchise_manager` | Near-full (typically no hard deletes) |
| `salon.branch_manager` | One branch operations |
| `salon.staff` | Stylist / front desk — limited |
| Customer (no login) | Public booking + messages/receipts |

---

## 01 — WhatsApp Automation

**Category:** Communication  
**Status:** Missing  
**Priority:** High  
**Source:** Strategy doc; Saloenza §10 / Appendix C  
**As of:** 21 September 2026

---

### 1. Summary

Saloenza today only stores a **display WhatsApp number** on the salon profile (`saloons.whatsapp`). There is **no** WhatsApp Business API connection, template catalog, campaign engine, or automated journey for booking / reminder / no-show / post-service / rebooking / birthday / win-back.

This document defines the product and data changes needed so automation can run safely under Qatar market expectations (opt-in, bilingual templates later, audit trail).

---

### 2. Current state

| Capability | Today |
|------------|--------|
| WhatsApp number on business profile | Yes (`saloons.whatsapp`) |
| Email/SMS confirmations, reminders, cancellations | Yes (scheduled jobs) |
| Birthday / anniversary wishes (email/SMS) | Yes |
| Appointment reminder flag | `appointments.reminder_sent_at` |
| Full WA campaign / journey engine | **No** (Appendix C) |

Existing related fields to reuse:

- Customer: `phone`, `birthday`, `anniversary`, `last_birthday_wish_on`, `last_anniversary_wish_on`
- Appointment: `status`, `starts_at`, `reminder_sent_at`, `payment_reminder_sent_at`, `booking_source`
- Salon settings: SMS/email notification toggles via tenant settings

---

### 3. Change level (what kind of work)

| Layer | Change intensity | Notes |
|-------|------------------|--------|
| **Integrations** | High | WhatsApp Cloud API / BSP (e.g. Meta Cloud API, Twilio, MessageBird, local Qatar BSP) |
| **Database** | High | Connection, templates, journeys, message log, opt-in/consent |
| **Background jobs** | High | Trigger engine + retry + rate limits |
| **API** | High | Config, templates, campaigns, logs, opt-out webhooks |
| **UI (SPA)** | High | Settings, journey builder (v1 can be preset journeys), inbox/log |
| **Permissions / plans** | Medium | Gate on Pro/Enterprise; new permission codes |
| **Compliance** | High | Opt-in, template approval, quiet hours, unsubscribe |

**Engineering size (indicative):** Large (multi-sprint). Do **not** start with a free-form “campaign builder”; ship **preset journeys** first.

---

### 4. Field & entity changes (proposed)

#### 4.1 New / extended entities

##### A. `whatsapp_connections` (per salon)

| Field | Type / notes | Purpose |
|-------|----------------|---------|
| `id` | PK | |
| `saloon_id` | FK | Tenant |
| `provider` | enum: `meta_cloud`, `twilio`, `other` | BSP |
| `phone_number_id` | string | Provider ID |
| `display_phone` | string | E.164 |
| `waba_id` | string nullable | Business account |
| `access_token_encrypted` | text | Secrets vault |
| `status` | enum: `pending`, `connected`, `disconnected`, `error` | |
| `quality_rating` | string nullable | Provider quality |
| `connected_at` / `last_error` | timestamps / text | Ops |
| `created_by` | user FK | Audit |

##### B. `whatsapp_message_templates`

| Field | Notes |
|-------|--------|
| `saloon_id` (nullable = platform defaults) | Tenant override of platform templates |
| `code` | e.g. `booking_confirmed`, `reminder_24h`, `no_show`, `post_service`, `rebook`, `birthday`, `winback` |
| `language` | `en`, `ar`, … |
| `provider_template_name` | Meta-approved name |
| `provider_template_id` | |
| `body_preview` | For UI |
| `variables_schema` | JSON list of placeholders |
| `status` | `draft`, `pending_approval`, `approved`, `rejected`, `paused` |
| `is_active` | bool |

##### C. `whatsapp_journeys` (preset automation definitions)

| Field | Notes |
|-------|--------|
| `saloon_id` | |
| `code` | journey key |
| `name` | |
| `is_enabled` | |
| `trigger_type` | see §6 |
| `trigger_config` | JSON (e.g. hours before, days since last visit) |
| `channel_priority` | JSON: prefer WA → SMS → email |
| `quiet_hours_start` / `quiet_hours_end` | salon local time |
| `cooldown_days` | avoid spam |
| `plan_required` | Free/Basic/Pro/Enterprise |

##### D. `whatsapp_journey_steps`

| Field | Notes |
|-------|--------|
| `journey_id` | |
| `sort_order` | |
| `delay_minutes` | relative to trigger |
| `template_code` | |
| `condition_json` | e.g. only if still `scheduled` |
| `stop_on` | events that cancel remaining steps |

##### E. `customer_communication_consents`

| Field | Notes |
|-------|--------|
| `customer_id`, `saloon_id` | |
| `channel` | `whatsapp`, `sms`, `email` |
| `purpose` | `transactional`, `marketing` |
| `status` | `opted_in`, `opted_out`, `unknown` |
| `source` | `booking_form`, `pos`, `import`, `reply_STOP` |
| `captured_at`, `captured_by` | |

##### F. `whatsapp_messages` (send/receive log)

| Field | Notes |
|-------|--------|
| `saloon_id`, `branch_id` nullable, `customer_id` nullable | |
| `appointment_id` nullable | Link |
| `direction` | `outbound`, `inbound` |
| `journey_code` / `template_code` | |
| `provider_message_id` | |
| `to_phone` / `from_phone` | |
| `status` | `queued`, `sent`, `delivered`, `read`, `failed`, `received` |
| `failure_reason` | |
| `payload_snapshot` | JSON (redact tokens) |
| `sent_at`, `delivered_at`, `read_at` | |

##### G. Salon / settings extensions

| Location | Field | Notes |
|----------|-------|--------|
| `saloons` or `salon_settings` | `whatsapp_automation_enabled` | Master switch |
| tenant settings | `whatsapp.default_language` | `en`/`ar` |
| tenant settings | `whatsapp.marketing_enabled` | Separate from transactional |
| keep | `saloons.whatsapp` | Display fallback if not connected |

#### 4.2 Existing fields to extend (light touch)

| Entity | Change |
|--------|--------|
| `appointments` | Optional: `whatsapp_reminder_sent_at`, `post_service_message_sent_at` (or rely solely on message log) |
| `customers` | Optional denorm: `whatsapp_opt_in`, `last_whatsapp_contact_at` for list filters |
| Public booking form | Consent checkboxes → write consent rows |

---

### 5. Role impact

#### New permissions (suggested)

| Permission code | Meaning |
|-----------------|---------|
| `whatsapp.view` | See connection status + message log |
| `whatsapp.manage` | Connect/disconnect, edit journeys, templates |
| `whatsapp.send_manual` | One-off approved template send |
| `whatsapp.view_inbox` | Read inbound (needed for doc 14 later) |

#### Per role

| Role | Configure connection | Enable journeys | Edit templates | View logs | Manual send | Customer consent edit |
|------|----------------------|-----------------|----------------|-----------|-------------|------------------------|
| **Platform Super Admin** | Platform defaults + force-disconnect for abuse | Platform catalog | Platform master templates | Cross-tenant support view | No (ops only) | No |
| **Franchise Owner** | Yes | Yes (all branches) | Map provider names / languages | Yes (all branches) | Yes | Yes |
| **Franchise Manager** | Yes (if permitted) | Yes | Limited | Yes | Yes | Yes |
| **Branch Manager** | No (salon-level) | Toggle branch-scoped journeys if supported | No | Own branch | Own branch | Own branch customers |
| **Staff** | No | No | No | No (or own clients only if later) | Optional “Send reminder” if given `whatsapp.send_manual` | Capture consent at POS/booking |
| **Customer** | — | — | — | — | Reply STOP / START | Sees consent on booking form |
| **Affiliate** | No touch | | | | | |

#### UI surfaces per role

- **Owner/Manager:** Settings → WhatsApp → Connection; Automations list (7 presets); Message log; Consent report  
- **Branch Manager:** Branch message log; enable/disable branch participation  
- **Staff/Reception:** Consent checkbox on customer create/edit and checkout; optional “Resend confirmation” button  
- **Platform Admin:** Provider health, template approval status, abuse / quality alerts

---

### 6. Business logic

#### 6.1 Journey catalog (v1 presets)

| Code | Trigger | Default timing | Stop conditions | Channel |
|------|---------|----------------|-----------------|---------|
| `booking_confirmed` | Appointment created (`scheduled`/`confirmed`) from internal/POS/self_booking | Immediate | Cancelled before send | Transactional WA |
| `reminder_24h` | Appointment still active | 24h before `starts_at` | Cancelled / completed / no-show / already started | Transactional |
| `reminder_2h` | Same | 2h before | Same | Transactional |
| `no_show_followup` | Status → `no-show` | +30–60 min | Customer rebooked same day | Marketing **or** transactional (policy decision) |
| `post_service_thanks` | Status → `completed` | +1–2 hours | None (unless opt-out marketing) | Transactional thank-you; review ask is marketing (see doc 17) |
| `rebook_nudge` | Completed + no future booking | +X days (salon config, default 28/45 by service category later) | New booking exists | Marketing |
| `birthday` | Customer birthday | Morning of day (quiet hours) | Already wished this year | Marketing |
| `winback` | Lapsing customer flag (doc 05) | When cohort generated | Opt-out / recent booking | Marketing |

#### 6.2 Send rules (global)

1. **Master switch:** salon `whatsapp_automation_enabled` AND connection `status=connected`.  
2. **Consent:**  
   - Transactional (confirm/remind/cancel): allow if phone present and not hard-blocked; still respect explicit WA opt-out if stored.  
   - Marketing (birthday, win-back, rebook): require `opted_in` for WhatsApp marketing.  
3. **Quiet hours:** queue until next allowed window (salon timezone).  
4. **Cooldown:** same journey+customer not more than once per `cooldown_days`.  
5. **Idempotency:** unique key `(appointment_id, journey_code, step_id)` or `(customer_id, journey_code, period_key)`.  
6. **Fallback:** if WA fails or not connected → existing SMS/email path (current reminder jobs).  
7. **Template variables:** customer name, salon name, branch, service names, date/time, staff name, booking link, unpaid amount (payment reminder).  
8. **Rate limits:** respect provider limits; backlog with retries (exponential backoff).  
9. **Status sync:** webhooks update delivered/read/failed.  
10. **Cancellation cascade:** if appointment cancelled, cancel pending queued messages for that appointment.

#### 6.3 Opt-out handling

- Inbound `STOP` / Arabic equivalents → set marketing WhatsApp to `opted_out`; acknowledge once.  
- Transactional may continue per local rules — **open legal decision for Qatar**.  
- Staff must see consent badge on customer profile.

#### 6.4 Plan gating (suggested)

| Plan | Access |
|------|--------|
| Free / Basic | Display number only; maybe manual “Open WhatsApp” deep link |
| Pro | Preset journeys + log |
| Enterprise | Custom delays, multi-language templates, higher volume, multi-number |

---

### 7. Dependencies

- **Blocks / feeds:** Win-back (05), marketing automation (07), two-way concierge (14), review requests (17), CLV prioritization (18).  
- **Depends on:** Reliable customer phone normalization (E.164), salon timezone, appointment status discipline.  
- **External:** Meta Business verification, template approval lag (plan for days/weeks).

---

### 8. Phasing

| Phase | Scope |
|-------|--------|
| **P0** | Connection + consent + message log + booking confirm + 24h reminder |
| **P1** | No-show, post-service, birthday |
| **P2** | Rebook + win-back hooks |
| **P3** | Journey editor UI (optional); A/B; analytics on delivery→rebook |

---

### 9. Open decisions

1. Preferred BSP for Qatar (Meta direct vs local aggregator).  
2. Are rebook/win-back treated as marketing (need opt-in) or service messages?  
3. Store tokens in DB encrypted vs secrets manager.  
4. One WA number per salon vs per branch.  
5. Whether Staff may send any manual WA without manager approval.

---

### 10. Acceptance criteria (planning)

- Owner can connect WA and enable at least confirm + reminder without engineering help.  
- Messages appear in an auditable log with delivery status.  
- Marketing journeys never send without opt-in.  
- Cancelling an appointment suppresses pending reminders.  
- When WA is disconnected, SMS/email reminders still work as today.

---

## 02 — AI Insights Dashboard (“Salon Intelligence”)

**Category:** Analytics  
**Status:** Missing  
**Priority:** High  
**Source:** Strategy doc  
**As of:** 21 September 2026

---

### 1. Summary

Strategy positioning needs a **Salon Intelligence** layer: overdue/lapsing customers, day-of-week imbalance, category revenue trends, and revenue-recovery estimates. Saloenza already has Pro+ analytics (`analytics/summary`, P&L, outstanding payments, staff earnings, product sales) but **no insight cards, anomaly flags, or recovery estimates**.

This is primarily a **read-model / computation + UI** feature on existing data — not a new operational module.

---

### 2. Current state

| Exists | Gap |
|--------|-----|
| Appointment statuses incl. `no-show`, `cancelled`, `completed` | No “overdue customer” detection UI |
| Analytics summary, P&L, expenses | No day-of-week heatmap / imbalance callouts |
| Category + services catalog | No category trend insight cards |
| Customer history via appointments | No “customers about to lose” list (see also doc 05) |
| Staff earnings | No staffing vs demand imbalance insight |
| Inventory reorder levels | Stock insights belong more to doc 03 |

---

### 3. Change level

| Layer | Intensity | Notes |
|-------|-----------|--------|
| **Database** | Low–Medium | Optional cached insight snapshots; mostly computed queries |
| **Services / jobs** | Medium | Nightly or hourly insight calculator |
| **API** | Medium | `/analytics/insights` (or similar) |
| **UI** | High | Dashboard cards, drill-downs, export |
| **Permissions** | Low | Reuse `analytics.view`; maybe `analytics.insights.view` |
| **ML / AI** | Low for v1 | Rule-based heuristics first; optional LLM narrative later |
| **Plan gate** | Medium | Pro / Enterprise differentiator |

**Important:** v1 should be **deterministic rules + formulas**, branded as “Intelligence.” True ML can wait.

---

### 4. Field & entity changes

#### 4.1 Optional cache: `salon_insight_snapshots`

| Field | Notes |
|-------|--------|
| `saloon_id`, `branch_id` nullable (`null` = all branches) | Scope |
| `as_of_date` | |
| `insight_type` | enum codes below |
| `severity` | severity: `info`, `warn`, `critical` |
| `title`, `summary` | Human text (EN; AR later) |
| `metrics_json` | Numbers for UI charts |
| `entity_refs_json` | IDs of customers/services/staff for drill-down |
| `estimated_revenue_impact` | decimal nullable |
| `recommended_action_code` | e.g. `run_winback`, `shift_staff`, `promo_offpeak` |
| `expires_at` | Recalc freshness |

#### 4.2 Optional config: `salon_insight_settings`

| Field | Default idea | Purpose |
|-------|--------------|---------|
| `lapse_days_threshold` | 45 / 60 | Overdue customer |
| `high_value_spend_threshold` | salon currency amount | Prioritize |
| `imbalance_variance_pct` | e.g. 25% | Day-of-week flag |
| `lookback_days` | 90 | Trends |
| `recovery_rate_assumption` | e.g. 15% | Revenue recovery estimate |
| `enabled_insight_types` | JSON | Toggle cards |

#### 4.3 No mandatory changes to core tables

Reuse: `appointments`, `appointment_services`, `customers`, `expenses`, `branch_product_stocks`, `users`, categories/services.

Denorm optional on `customers`: `last_visit_at`, `lifetime_spend`, `visit_count` (also useful for docs 05 & 18).

---

### 5. Role impact

| Role | View dashboard | Configure thresholds | Act on recommendations | Export |
|------|----------------|----------------------|------------------------|--------|
| **Franchise Owner** | All branches + compare | Yes | Launch win-back / promo (if modules exist) | Yes |
| **Franchise Manager** | All branches | Yes | Yes | Yes |
| **Branch Manager** | Own branch only | Limited (branch overrides) | Branch actions | Own branch |
| **Staff** | No (or personal “your idle slots” later) | No | No | No |
| **Platform Admin** | Anonymized product analytics optional | Platform defaults | Support | — |
| **Customer / Affiliate** | No | | | |

#### UI placement

- New **Salon Intelligence** page under Analytics (Pro+).  
- 4–6 cards on home dashboard for Owner/Manager.  
- Each card → drill-down list (customers, days, categories).

---

### 6. Business logic — insight catalog (v1)

#### 6.1 Overdue / lapsing customers

**Definition (default):** Customer with ≥1 completed visit, `last_visit_at` older than `lapse_days_threshold`, and no future `scheduled`/`confirmed` appointment.

**Metrics:** count, estimated recoverable revenue = `AVG(ticket of last N visits) × recovery_rate_assumption × count`.

**Action codes:** `open_winback_list`, `send_whatsapp_winback` (needs docs 01 & 05).

#### 6.2 Day-of-week imbalance

**Definition:** For last `lookback_days`, compute completed revenue (or booking count) by weekday. Flag days where value is **below** mean by `imbalance_variance_pct`, and days **above** by same margin (overflow risk).

**Metrics:** heatmap; peak staff hours vs demand (if weekly_schedule available).

**Action:** suggest off-peak promo (doc 20) or staffing shift (doc 19).

#### 6.3 Category revenue trends

**Definition:** Compare category revenue this period vs prior period (WoW / MoM). Flag categories with decline > X% or growth > X%.

**Data:** sum of `appointment_services` / products joined to category for `completed` appointments.

#### 6.4 No-show & cancellation loss

**Definition:** Count/value of `no-show` and late cancels; estimate lost revenue = sum of booked `grand_total` (or service prices) for those rows.

**Action:** enable no-show protection (doc 13) for repeat offenders.

#### 6.5 Staff utilization imbalance

**Definition:** Completed service minutes per staff vs available weekly_schedule minutes. Flag under/over utilized stylists.

#### 6.6 Revenue recovery estimate (portfolio)

Roll-up card:

`recovery_estimate = sum(insight.estimated_revenue_impact)` for active warn/critical insights.

Show confidence as **assumption-based**, not guarantee — label clearly for sales honesty.

#### 6.7 Computation schedule

- Nightly job per salon (timezone).  
- On-demand refresh for Owner (rate-limited).  
- Branch filter: `branch_id` null = franchise roll-up.

#### 6.8 “AI” narrative (optional P2)

Generate 3–5 bullet “briefing” from metrics using templates or LLM. **Must** cite underlying numbers; never invent customers.

---

### 7. Dependencies

- Stronger with: win-back (05), WhatsApp (01), CLV (18), dynamic pricing (20), branch benchmarking (08).  
- Works standalone as read-only analytics.

---

### 8. Phasing

| Phase | Deliver |
|-------|---------|
| P0 | Overdue customers + no-show loss + day-of-week cards |
| P1 | Category trends + staff utilization + recovery roll-up |
| P2 | Cached snapshots, bilingual text, action buttons into other modules |
| P3 | Optional LLM briefing |

---

### 9. Open decisions

1. Default lapse days for Qatar salons (45 vs 60).  
2. Recovery % assumption editable per salon or locked platform default.  
3. Whether Staff see any personal insights.  
4. Insight language: English-only until Arabic pack (doc 12).

---

### 10. Acceptance criteria

- Owner on Pro sees ≥4 insight types with drill-downs.  
- Branch Manager cannot see other branches’ customer lists.  
- Numbers reconcile with existing analytics for the same date range (± rounding).  
- Empty states when salon is new (&lt; 14 days data).

---

## 03 — Smart / Predictive Inventory

**Category:** Inventory  
**Status:** Partial  
**Priority:** Medium  
**Source:** Strategy doc; Saloenza §4.8  
**As of:** 21 September 2026

---

### 1. Summary

Saloenza already has **branch stock**, **reorder levels**, **movements**, **suppliers**, and **purchase orders** (draft → ordered → received). What is missing is **classification and forecasting**: low stock (smart), fast-moving, dead stock, expiring, and days-to-stockout — plus suggested PO quantities.

---

### 2. Current state (reuse)

| Entity | Useful fields |
|--------|----------------|
| `branch_product_stocks` | `quantity_on_hand`, `reorder_level`, `max_stock`, `cost_price`, `selling_price`, `supplier_id` |
| `inventory_movements` | `type` sale/purchase/adjustment, qty change, timestamps |
| `purchase_orders` + items | Ordering workflow |
| `products` | `sku`, `brand`, `unit`, `category_id` |

**Gap fields today:** no expiry dates, no velocity metrics, no forecast outputs, no ABC classification.

---

### 3. Change level

| Layer | Intensity |
|-------|-----------|
| Database | Medium (product lot/expiry + metrics tables) |
| Jobs | Medium (nightly velocity & stockout calc) |
| API | Medium |
| UI | Medium–High (new inventory intelligence views + PO suggestions) |
| Permissions | Low (reuse `inventory.view` / `inventory.manage`) |
| Plan gate | Inventory module + Pro+ for predictive |

---

### 4. Field & entity changes

#### 4.1 Extend `branch_product_stocks` (or parallel metrics table)

| Field | Notes |
|-------|--------|
| `avg_daily_sales_7d` | Computed |
| `avg_daily_sales_30d` | Computed |
| `avg_daily_sales_90d` | Computed |
| `days_of_cover` | `qty / avg_daily_sales` (null if zero velocity) |
| `days_to_stockout` | Same as cover when no inbound PO; else adjust for ordered qty |
| `velocity_class` | `fast`, `medium`, `slow`, `dead` |
| `abc_class` | `A`, `B`, `C` optional |
| `suggested_reorder_qty` | Based on lead time + target cover |
| `metrics_updated_at` | |

#### 4.2 New: `product_lots` / batches (for expiry)

| Field | Notes |
|-------|--------|
| `branch_id`, `product_id` | |
| `lot_code` | optional |
| `quantity` | |
| `received_at` | |
| `expires_on` | **date** — critical for cosmetics/chemicals |
| `purchase_order_item_id` | nullable |
| `cost_price` | |

**Business rule:** sales consume FEFO (first expiry first out) when lots exist; else fall back to bulk qty.

#### 4.3 Extend `products` or salon product settings

| Field | Notes |
|-------|--------|
| `track_expiry` | bool |
| `default_lead_time_days` | supplier lead time |
| `target_days_of_cover` | e.g. 21 |
| `dead_stock_after_days` | e.g. 90 with zero sales |

#### 4.4 New: `inventory_alerts`

| Field | Notes |
|-------|--------|
| `type` | `low_stock`, `stockout_risk`, `dead_stock`, `expiring_soon`, `overstock` |
| `severity` | |
| `product_id`, `branch_id` | |
| `message`, `metrics_json` | |
| `status` | `open`, `acked`, `resolved` |
| `suggested_po_id` | if auto-drafted |

#### 4.5 PO enhancement

| Field on PO / item | Notes |
|--------------------|--------|
| `source` | `manual`, `smart_suggestion` |
| `suggested_by_job_at` | |
| Item: `reason_code` | low_stock / expiry replace / etc. |

---

### 5. Role impact

| Role | View classes & forecasts | Ack alerts | Create PO from suggestion | Configure lead times / cover | Edit lots / expiry |
|------|--------------------------|------------|---------------------------|------------------------------|--------------------|
| **Franchise Owner** | All branches | Yes | Yes | Yes | Yes |
| **Franchise Manager** | All | Yes | Yes | Yes | Yes |
| **Branch Manager** | Own branch | Yes | Yes | Branch defaults | Yes |
| **Staff** | No | No | No | No | No |
| **Platform Admin** | — | — | — | Platform default formulas | — |

UI: Inventory → **Intelligence** tab (Fast / Dead / Expiring / Stockout risk) + “Generate suggested PO”.

---

### 6. Business logic

#### 6.1 Velocity classification (defaults)

Using 30-day avg daily sales:

| Class | Rule (configurable) |
|-------|---------------------|
| `fast` | Top 20% by units or avg_daily ≥ threshold |
| `medium` | Middle |
| `slow` | Bottom with some sales |
| `dead` | Zero sales for `dead_stock_after_days` AND qty &gt; 0 |

#### 6.2 Days to stockout

```
effective_daily = avg_daily_sales_30d (fallback 90d, then 7d)
inbound = sum(quantity_ordered - quantity_received) for POs in ordered status
days_to_stockout = (quantity_on_hand + inbound) / effective_daily
```

Alert `stockout_risk` when `days_to_stockout` ≤ lead_time_days (or ≤ 7).

#### 6.3 Low stock (smart)

Not only `qty <= reorder_level`, but also:

`qty <= effective_daily * lead_time_days` (demand-aware).

#### 6.4 Expiring

Alert when `expires_on` within N days (e.g. 30/60) and qty remaining &gt; 0.  
Suggest: discount retail push, stop reorder, transfer to busier branch (multi-branch).

#### 6.5 Suggested reorder qty

```
target = effective_daily * target_days_of_cover
suggested = max(0, target - quantity_on_hand - inbound)
round to pack size if defined
```

Cap by `max_stock` if set.

#### 6.6 Overstock

`days_of_cover` &gt; 2 × target_days_of_cover → `overstock` alert.

#### 6.7 Automation level (v1 vs v2)

| v1 | v2 |
|----|----|
| Show classifications + alerts | Auto-create **draft** POs |
| One-click “Add to draft PO” | Multi-supplier split |
| Manual receive still as today | Transfer suggestions across branches |

**Do not** auto-place orders with suppliers without human confirm.

---

### 7. Dependencies

- Existing inventory module + plan entitlement.  
- Feeds AI insights (02) optionally.  
- Expiry tracking needs discipline at PO receive UI.

---

### 8. Phasing

| Phase | Scope |
|-------|--------|
| P0 | Velocity metrics + fast/dead/low/stockout views; no lots |
| P1 | Suggested reorder qty + draft PO from selection |
| P2 | Lots + expiry alerts + FEFO |
| P3 | Cross-branch transfer suggestions |

---

### 9. Open decisions

1. Is expiry tracking required for Qatar cosmetics compliance story?  
2. ABC by revenue vs units?  
3. Who may delete lots after receive?  
4. Whether cost averaging changes when lots have different costs.

---

### 10. Acceptance criteria

- Branch Manager sees stockout risk list that matches movement history.  
- Suggested qty never exceeds `max_stock`.  
- Dead stock list excludes products with sales inside threshold.  
- Receiving a PO updates metrics within one job cycle.

---

## 04 — Full Commission Engine

**Category:** Staff  
**Status:** Partial  
**Priority:** High  
**Source:** Strategy doc; Saloenza §4.9  
**As of:** 21 September 2026

---

### 1. Summary

Today staff have a **single flat** `users.commission_rate` (% of line revenue) applied by `StaffEarningsService` on completed/in-progress appointment lines. Strategy “Pro” positioning needs a **rules engine**: %, fixed amount, service/product-specific, tiered, per-staff rates, discount/no-show rules, and automatic breakdown for payout periods.

---

### 2. Current state

| Item | Today |
|------|--------|
| Field | `users.commission_rate` (percent) |
| Salary field | `users.per_month_salary` (display / P&L estimate only) |
| Calculation | `commission = revenue * rate / 100` per assigned line |
| Affiliate commissions | Separate system — **do not conflate** |
| Payout runs / slips | None |
| Service-specific rates | None |
| Discount impact rules | None |
| No-show commission rules | None |

---

### 3. Change level

| Layer | Intensity | Notes |
|-------|-----------|--------|
| Database | High | Rules, assignments, commission lines, payout periods |
| Domain services | High | Replace flat calc with rule resolver |
| API | High | CRUD rules + earnings breakdown |
| UI | High | Commission setup wizard + payslip-like view |
| Permissions | Medium | Separate manage vs view earnings |
| Migration | Medium | Map existing `commission_rate` → default rule |
| Plan gate | Pro+ story | |

**Engineering size:** Large. Critical for sales credibility before promising Pro commissions.

---

### 4. Field & entity changes

#### 4.1 Keep but demote

| Field | Change |
|-------|--------|
| `users.commission_rate` | Become **fallback default %** OR migrate into a personal default rule and deprecate later |
| `users.per_month_salary` | Remains for base salary display; payroll doc 09 may consume later |

#### 4.2 New: `commission_schemes`

| Field | Notes |
|-------|--------|
| `saloon_id` | |
| `name` | e.g. “2026 Stylist Pro” |
| `is_default` | |
| `is_active` | |
| `currency` | salon currency |
| `effective_from` / `effective_to` | |

#### 4.3 New: `commission_rules`

| Field | Notes |
|-------|--------|
| `scheme_id` | |
| `priority` | Higher wins when multiple match |
| `name` | |
| `applies_to` | `service`, `product`, `all` |
| `service_id` / `category_id` / `product_id` | nullable filters |
| `staff_user_id` | nullable = all staff on scheme |
| `role_id` | optional |
| `calc_type` | `percent_of_revenue`, `percent_of_net`, `fixed_per_line`, `fixed_per_minute`, `tiered_percent` |
| `rate_value` | percent or fixed amount |
| `tier_json` | e.g. `[{min:0,max:5000,rate:10},{min:5001,rate:15}]` monthly revenue |
| `include_discounts` | bool — commission on pre or post discount |
| `min_line_price` | ignore tiny lines |
| `is_active` | |

#### 4.4 New: `staff_commission_assignments`

| Field | Notes |
|-------|--------|
| `user_id`, `saloon_id`, `branch_id` nullable | |
| `scheme_id` | |
| `effective_from` / `effective_to` | |
| `override_percent` | optional personal override |

#### 4.5 New: `commission_line_items` (immutable earn records)

| Field | Notes |
|-------|--------|
| `saloon_id`, `branch_id` | |
| `user_id` (earner) | |
| `appointment_id`, `appointment_service_id` / `appointment_product_id` | |
| `basis_amount` | amount used for calc |
| `calc_type`, `rate_applied`, `rule_id` | audit |
| `commission_amount` | |
| `status` | `pending`, `approved`, `excluded`, `paid` |
| `exclusion_reason` | e.g. `no_show`, `full_discount`, `void` |
| `earned_on` | date of completion |
| `payout_period_id` | nullable |

#### 4.6 New: `commission_payout_periods`

| Field | Notes |
|-------|--------|
| `saloon_id` | |
| `period_start`, `period_end` | |
| `status` | `open`, `locked`, `paid` |
| `total_commission` | |
| `locked_at`, `locked_by` | |
| `notes` | |

#### 4.7 Rule flags for edge events

Store in scheme or salon settings:

| Setting | Meaning |
|---------|---------|
| `commission_on_no_show` | default false → exclude |
| `commission_on_cancelled` | false |
| `commission_when_payment_unpaid` | pending until paid vs earn on complete |
| `split_mode` | when multiple staff on multi-service appointment — already per-line `staff_id` |
| `discount_bearer` | reduce commission proportionally vs salon absorbs |

---

### 5. Role impact

#### Permissions

| Code | Purpose |
|------|---------|
| `commissions.view` | See rules (managers+) |
| `commissions.manage` | Edit schemes/rules |
| `commissions.approve` | Lock period / mark paid |
| `staff.earnings.view` | Existing — staff own lines |

| Role | Manage schemes | Assign staff to scheme | View all breakdowns | Lock/pay period | View own earnings |
|------|----------------|------------------------|---------------------|-----------------|-------------------|
| **Franchise Owner** | Yes | Yes | Yes | Yes | Yes |
| **Franchise Manager** | Yes | Yes | Yes | Optional | Yes |
| **Branch Manager** | No (or limited) | Assign within branch if allowed | Own branch | No | Yes |
| **Staff** | No | No | No | No | Own only (itemized) |
| **Platform Admin** | — | — | Support | — | — |

#### UI

- Settings → **Commission schemes** (Owner).  
- Staff profile → assigned scheme + effective dates.  
- Earnings page → line-level breakdown (rule name, basis, amount).  
- Period close wizard (Owner/Manager).

---

### 6. Business logic

#### 6.1 When to create commission lines

**Trigger:** Appointment (or line) reaches `completed` (recommended).  
Optionally create `pending` on `in-progress` but finalize on completed + payment rule.

#### 6.2 Rule resolution order

For each service/product line with `staff_id`:

1. Find active assignment for staff at `earned_on`.  
2. Load scheme rules sorted by `priority` DESC.  
3. Match first rule where filters fit (specific service &gt; category &gt; all).  
4. If none, fallback to `users.commission_rate` flat %.  
5. Compute amount; write `commission_line_items`.

#### 6.3 Calculation types

| Type | Formula |
|------|---------|
| `percent_of_revenue` | `line_price * qty * rate%` (gross) |
| `percent_of_net` | `(line_price * qty - allocated_discount) * rate%` |
| `fixed_per_line` | `rate_value` once per line |
| `fixed_per_minute` | `duration_minutes * rate_value` |
| `tiered_percent` | Based on staff’s period-to-date completed revenue; apply tier rate to **this line** or whole period (choose one; recommend period-end true-up) |

#### 6.4 Discount rules

- Allocate appointment-level `discount` across lines proportionally by price.  
- If `include_discounts=false`, basis = gross before discount.  
- If 100% discount → commission 0 (or exclusion_reason `full_discount`).

#### 6.5 No-show / cancel

- Status `no-show` or `cancelled` → no new commission; void any pending lines.  
- If already paid commission then reversed — **v2** clawback; v1 prevent earn until completed only.

#### 6.6 Product vs service

Separate rules often: services 40%, retail 10%. Engine must support `applies_to=product`.

#### 6.7 Tiered true-up (if chosen)

At period lock: recompute all lines in period with final tier; adjust with correction lines.

#### 6.8 Payout period

1. Owner selects date range.  
2. System lists pending/approved lines.  
3. Lock → status `locked` (immutable).  
4. Mark `paid` (manual; no bank integration required in v1).  
5. Staff see “Paid” vs “Pending”.

#### 6.9 Migration path

- For each staff with `commission_rate` &gt; 0: create default scheme rule `all / percent_of_revenue / rate`.  
- Earnings history before launch remains old formula (document cutover date).

---

### 7. Dependencies

- Clean `staff_id` on appointment lines (already exists).  
- Payment policy decision affects “earn when unpaid”.  
- Advanced payroll (09) may consume locked periods later.  
- Do **not** mix with `AffiliateCommission`.

---

### 8. Phasing

| Phase | Scope |
|-------|--------|
| P0 | Schemes + % / fixed / service-or-product-specific + line items + staff breakdown UI |
| P1 | Discount allocation + unpaid/no-show rules + payout periods |
| P2 | Tiered with true-up |
| P3 | Clawbacks, multi-currency, tips split |

---

### 9. Open decisions

1. Earn on **completed** vs **paid**.  
2. Tier applied per line live vs period-end true-up.  
3. Can Branch Manager edit rates? (Recommend no.)  
4. Retail commission to cashier vs treating staff only.  
5. Tips: in or out of commission basis?

---

### 10. Acceptance criteria

- Two staff on same scheme with different service rules get different amounts on one multi-service ticket.  
- Staff see rule name on each earning line.  
- Changing a rule does **not** rewrite locked period lines.  
- Existing flat-rate salons behave identically after migration until they edit schemes.

---

## 05 — Customer Win-Back / Retention Automation

**Category:** CRM  
**Status:** Missing  
**Priority:** High  
**Source:** Strategy doc  
**As of:** 21 September 2026

---

### 1. Summary

Saloenza has customers, tags, visit history (via appointments), and occasion wishes — but **no detection of lapsing customers** and **no one-click re-engagement campaign**. This feature is the operational companion to Salon Intelligence (02) and WhatsApp/marketing automation (01, 07).

---

### 2. Current state

| Exists | Missing |
|--------|---------|
| `customers` + tags | Lapse detection job |
| Appointment history | “About to lose” queue |
| Birthday/anniversary wish tracking fields | Win-back send tracking |
| SMS/email channels | Coordinated win-back journey |
| No CLV yet (doc 18) | Prioritized targeting |

---

### 3. Change level

| Layer | Intensity |
|-------|-----------|
| Database | Medium |
| Jobs | Medium |
| API | Medium |
| UI | Medium–High |
| Messaging | Depends on 01 / existing SMS-email |
| Permissions | Medium (`crm.retention.*`) |

Can ship **detection + list + manual message** before full WhatsApp automation.

---

### 4. Field & entity changes

#### 4.1 Customer denormalized metrics (recommended)

Add to `customers` or `customer_salon_stats` pivot:

| Field | Notes |
|-------|--------|
| `last_visit_at` | Max completed appointment |
| `last_visit_branch_id` | |
| `visit_count` | Completed count |
| `lifetime_spend` | Sum paid/completed totals |
| `avg_ticket` | |
| `fav_service_id` / `fav_staff_id` | Mode of history |
| `expected_revisit_days` | Median days between visits (nullable) |
| `lapse_status` | `active`, `at_risk`, `lapsed`, `lost` |
| `lapse_status_updated_at` | |

#### 4.2 New: `retention_policies`

| Field | Notes |
|-------|--------|
| `saloon_id` | |
| `name` | |
| `at_risk_after_days` | e.g. 1.2 × expected or fixed 40 |
| `lapsed_after_days` | e.g. 60 |
| `lost_after_days` | e.g. 120 |
| `min_visits_required` | e.g. 1 or 2 before eligible |
| `exclude_tag_ids` | JSON — e.g. “Do not contact” |
| `channels_allowed` | whatsapp/sms/email |
| `is_active` | |

#### 4.3 New: `retention_cohorts` / campaign runs

| Field | Notes |
|-------|--------|
| `saloon_id`, `branch_id` nullable | |
| `policy_id` | |
| `name` | “Sept 2026 At-Risk” |
| `status` | `draft`, `ready`, `sending`, `completed`, `cancelled` |
| `segment_filter_json` | status, CLV tier, tags, branch |
| `template_channel` + `template_code` | |
| `scheduled_at` | |
| `created_by` | |
| `stats_json` | targeted, sent, failed, rebooked |

#### 4.4 New: `retention_cohort_members`

| Field | Notes |
|-------|--------|
| `cohort_id`, `customer_id` | |
| `lapse_status_at_add` | |
| `priority_score` | from CLV or spend |
| `message_status` | `pending`, `sent`, `skipped`, `failed`, `opted_out` |
| `sent_at` | |
| `response_appointment_id` | if rebooked within attribution window |
| `skip_reason` | |

#### 4.5 Attribution window setting

`rebook_attribution_days` (default 14): if customer books after send within window → count as recovered.

---

### 5. Role impact

| Role | Configure policy | Generate “about to lose” list | Create cohort / send | See recovery stats | Exclude customer |
|------|------------------|-------------------------------|----------------------|--------------------|------------------|
| **Franchise Owner** | Yes | Yes | Yes | Yes | Yes |
| **Franchise Manager** | Yes | Yes | Yes | Yes | Yes |
| **Branch Manager** | View / limited | Own branch | Own branch if allowed | Own branch | Own branch |
| **Staff** | No | No | No | No | Can tag “Do not contact” if permitted |
| **Customer** | — | — | — | — | Opt-out via channel |

UI: CRM → **Retention** → At risk / Lapsed / Lost tabs + “Start win-back”.

---

### 6. Business logic

#### 6.1 Nightly classification

For each customer linked to salon:

1. Update visit metrics from completed appointments.  
2. Compute `days_since_visit`.  
3. If future booking exists → force `active`.  
4. Else apply policy thresholds → `at_risk` / `lapsed` / `lost`.  
5. Optional: if `expected_revisit_days` known, `at_risk` when `days_since_visit > expected_revisit_days * 1.25`.

#### 6.2 Eligibility for messaging

Exclude when:

- Marketing consent not opted-in (for marketing channels).  
- Phone/email missing for chosen channel.  
- Has `exclude` tag.  
- Already messaged for same status within cooldown (e.g. 30 days).  
- `is_active=false` customer.

#### 6.3 One-click campaign flow

1. Manager opens At-Risk list (sorted by `priority_score` / lifetime_spend).  
2. Selects all or subset.  
3. Picks template (WhatsApp if 01 ready; else SMS/email).  
4. Confirms preview count.  
5. System creates cohort + members; queues sends.  
6. Job sends with quiet hours / rate limits.  
7. Dashboard shows sent % and rebooked % within attribution window.

#### 6.4 Priority score (v1 without full CLV)

```
priority = lifetime_spend * 0.5 + avg_ticket * 0.3 + visit_count * 0.2
```

Replace with CLV score when doc 18 ships.

#### 6.5 Success definition

Member `response_appointment_id` set when new appointment created after `sent_at` within attribution days (any branch or same branch — configurable).

#### 6.6 Insight handoff

Salon Intelligence card “Customers you’re about to lose” deep-links to this list with same filters (doc 02).

---

### 7. Dependencies

- Soft dependency: WhatsApp (01) for strategy narrative.  
- Soft: Marketing automation (07) for reusable journeys.  
- Soft: CLV (18) for better ranking.  
- Hard: Accurate completed appointment history + customer phone.

---

### 8. Phasing

| Phase | Scope |
|-------|--------|
| P0 | Metrics + lapse_status + list UI + export CSV |
| P1 | Cohort send via SMS/email + attribution stats |
| P2 | WhatsApp templates + one-click from Intelligence |
| P3 | Auto-enroll (policy: nightly add at_risk to drip) |

---

### 9. Open decisions

1. Fixed day thresholds vs personalized expected revisit.  
2. Auto-send vs always human confirm (recommend confirm for v1).  
3. Cross-branch vs home-branch attribution.  
4. Whether `lost` customers are hidden from active CRM searches.

---

### 10. Acceptance criteria

- Customer with visit 70 days ago and no future booking appears in Lapsed.  
- Booking a future appointment removes them from win-back lists on next refresh.  
- Opted-out customers never enter `sending` state.  
- Cohort stats show rebook count matching appointments in window.

---

## 06 — Loyalty, Memberships, Packages & Gift Cards

**Category:** Customer growth  
**Status:** Missing (explicitly out of scope — Appendix C)  
**Priority:** Medium  
**Source:** Saloenza Appendix C  
**As of:** 21 September 2026

---

### 1. Summary

Appendix C correctly excludes loyalty points, gift cards, and memberships. This document is a **go/no-go planning brief**: what would need to change if Saloenza later builds them for Qatar GTM, including packages (prepaid service bundles) which are adjacent and common in salons.

**Recommendation framing:** Treat as a **product decision**, not an assumption. If built, prefer **Packages + Gift Cards** before full points loyalty (higher ROI, clearer accounting).

---

### 2. Current state

- Appointments support services, products, discounts, partial payments.  
- No wallet, points balance, membership plan, package remainder, or gift card entities.  
- Customer has no portal login (Appendix C) — balances would be staff-managed + optional public code redeem.

---

### 3. Change level (if approved)

| Layer | Intensity | Why |
|-------|-----------|-----|
| Database | Very High | Ledgers, liabilities, redemptions |
| POS / checkout | Very High | Tender types, partial redeem |
| Accounting / P&L | High | Deferred revenue |
| API + UI | High | Sell, redeem, adjust, expire |
| Permissions | High | Fraud-sensitive adjustments |
| Compliance | Medium | Gift card consumer rules (jurisdiction) |
| Plan gate | Enterprise or add-on SKU | |

**Engineering size:** Very large. Multi-month if full suite.

---

### 4. Field & entity changes (proposed suite)

#### 4.1 Packages (service bundles) — suggested first

##### `packages`

| Field | Notes |
|-------|--------|
| `saloon_id` | |
| `name`, `description` | |
| `price`, `valid_days` | |
| `is_active` | |
| `branch_id` nullable | all or one |

##### `package_items`

| Field | Notes |
|-------|--------|
| `package_id`, `service_id` | |
| `quantity_total` | e.g. 5 haircuts |
| `unit_value` | for liability allocation |

##### `customer_packages`

| Field | Notes |
|-------|--------|
| `customer_id`, `package_id` | |
| `purchased_at`, `expires_at` | |
| `status` | `active`, `exhausted`, `expired`, `void` |
| `amount_paid`, `payment_ref` | |
| `sold_by`, `branch_id` | |

##### `customer_package_items`

| Field | Notes |
|-------|--------|
| Remaining qty per service | `quantity_remaining` |
| Link redemptions to `appointment_service_id` | |

#### 4.2 Gift cards

##### `gift_cards`

| Field | Notes |
|-------|--------|
| `saloon_id`, `code` (unique) | |
| `initial_balance`, `current_balance` | |
| `currency` | |
| `status` | `active`, `redeemed`, `expired`, `disabled` |
| `purchaser_customer_id` nullable | |
| `recipient_name` / phone | |
| `expires_at` | |
| `sold_appointment_id` / invoice | |

##### `gift_card_transactions`

| Field | Notes |
|-------|--------|
| `type` | `issue`, `redeem`, `adjust`, `expire` |
| `amount`, `balance_after` | |
| `appointment_id` nullable | |
| `created_by`, `reason` | |

#### 4.3 Memberships

##### `membership_plans`

| Field | Notes |
|-------|--------|
| `name`, `billing_period` | monthly/annual |
| `price`, `perks_json` | % off, included services |
| `is_active` | |

##### `customer_memberships`

| Field | Notes |
|-------|--------|
| `plan_id`, `customer_id` | |
| `status` | `active`, `past_due`, `cancelled` |
| `started_at`, `renews_at`, `cancelled_at` | |

**Note:** Membership billing needs payment method on file — heavy dependency (also doc 13).

#### 4.4 Loyalty points (last)

##### `loyalty_programs` + `loyalty_accounts` + `loyalty_ledger`

| Concept | Notes |
|---------|--------|
| Earn rules | % of spend, per service |
| Burn rules | points → discount |
| Expiry | points expire |
| Caps | max redeem % per ticket |

#### 4.5 Appointment / payment extensions

| Field / tender | Notes |
|----------------|--------|
| New payment_method values | `package`, `gift_card`, `loyalty` |
| `amount_paid` composition | May need `appointment_tenders` child table (cash + gift card split) |

**Strongly recommended new entity:** `appointment_payments` / tenders — today’s single payment fields on `appointments` will not scale.

---

### 5. Role impact

| Role | Sell package/GC | Redeem | Adjust balance / void | Configure plans | View liability report |
|------|-----------------|--------|----------------------|-----------------|----------------------|
| **Franchise Owner** | Yes | Yes | Yes (audit) | Yes | Yes |
| **Franchise Manager** | Yes | Yes | Limited | Yes | Yes |
| **Branch Manager** | Yes | Yes | No void high value | No | Branch |
| **Staff** | If permitted | Yes at POS | No | No | No |
| **Customer** | Buy via booking/POS | Present code | No | No | See balance if portal later |
| **Platform Admin** | — | — | Fraud support | Platform feature flags | — |

#### New permissions

`loyalty.view`, `loyalty.manage`, `packages.sell`, `packages.redeem`, `giftcards.sell`, `giftcards.redeem`, `giftcards.adjust`, `memberships.manage`.

---

### 6. Business logic (high level)

#### 6.1 Packages

1. Sell package → create `customer_packages` with full item quantities; record revenue (policy: recognize immediately vs deferred — **accounting decision**).  
2. On booking/checkout, staff selects package redemption → decrement remaining; line price may be 0 with tender `package`.  
3. Expire job: status `expired`; unused liability write-off policy.  
4. Cannot redeem expired/exhausted; cannot over-redeem.

#### 6.2 Gift cards

1. Issue with unique code; print/PDF/WhatsApp code.  
2. Redeem at POS: reduce balance; allow split tender.  
3. Adjustments require reason + manager permission.  
4. Expiry job; optional reactivation.

#### 6.3 Memberships

1. Active membership applies perk pricing at booking (auto discount rule).  
2. Renewal billing (manual or card-on-file).  
3. Cancel → perks stop at period end or immediate.

#### 6.4 Points

1. On paid completion → earn points.  
2. At checkout → redeem up to cap.  
3. Void appointment → reverse ledger entries.

#### 6.5 Fraud & audit

Every balance change writes ledger row; void requires dual control above threshold.

---

### 7. Decision framework (business)

| Option | Build effort | Market pull (Qatar salons) | Suggest |
|--------|--------------|----------------------------|---------|
| Stay out of scope | 0 | Honest sales | Default until Pro sales blocked |
| Packages only | High | Strong | Best first wedge |
| Packages + gift cards | Very high | Strong | Phase 2 |
| Full loyalty points | Very high | Mixed | Only if competitors force |
| Memberships | Very high | Medium | Needs payments maturity |

---

### 8. Dependencies

- Split tender payments model.  
- Optional customer portal for self-serve balance (Appendix C conflict).  
- WhatsApp to deliver gift codes (01).  
- Tax/GST treatment for gift cards — finance input needed.

---

### 9. Phasing (only if go)

| Phase | Scope |
|-------|--------|
| P0 | Packages sell + redeem + remaining UI |
| P1 | Gift cards + split tender |
| P2 | Liability / outstanding balances report |
| P3 | Memberships / points |

---

### 10. Open decisions

1. Revenue recognition for packages/gift cards.  
2. Cross-branch redemption (usually yes for franchise).  
3. Refund policy for unused packages.  
4. Keep Appendix C exclusion in marketing until P0 live.

---

### 11. Acceptance criteria (if built)

- Remaining package counts never go negative.  
- Gift card balance ledger always reconciles to `current_balance`.  
- Staff without adjust permission cannot increase balances.  
- P&L docs explain deferred vs recognized revenue.

---

## 07 — Marketing Automation on Segmentation / Tags

**Category:** Marketing  
**Status:** Partial  
**Priority:** Medium  
**Source:** Strategy doc; Saloenza §4.6  
**As of:** 21 September 2026

---

### 1. Summary

Tags and basic CRM exist (`CustomerTag`, name/color, customer pivots). Occasion wishes exist. What is missing is an **automation layer**: define a segment → schedule/trigger a campaign → send via WhatsApp/SMS/email → measure outcomes.

This is the “act on tags” layer sitting above win-back (05) and WhatsApp (01).

---

### 2. Current state

| Exists | Gap |
|--------|-----|
| Tags CRUD + assign | No saved segments with rules |
| Manual tagging | No auto-tag rules |
| Birthday/anniversary jobs | No general campaign scheduler |
| Analytics | No campaign conversion stats |

---

### 3. Change level

| Layer | Intensity |
|-------|-----------|
| Database | Medium–High |
| Jobs | Medium |
| API / UI | High (segment builder + campaign) |
| Channels | Depends on 01 + existing SMS/email |
| Permissions | Medium |

---

### 4. Field & entity changes

#### 4.1 Enhance tags (light)

| Field on `customer_tags` | Notes |
|--------------------------|--------|
| `is_system` | e.g. auto-managed |
| `category` | `marketing`, `ops`, `risk` |
| `description` | |

#### 4.2 New: `customer_segments`

| Field | Notes |
|-------|--------|
| `saloon_id` | |
| `name` | |
| `type` | `static` (manual list) / `dynamic` (rules) |
| `rules_json` | see §6 |
| `estimated_size` | cached |
| `is_active` | |
| `created_by` | |

#### 4.3 New: `marketing_campaigns`

| Field | Notes |
|-------|--------|
| `saloon_id` | |
| `name` | |
| `segment_id` | |
| `channel` | `whatsapp`, `sms`, `email` |
| `template_ref` | |
| `status` | `draft`, `scheduled`, `running`, `completed`, `cancelled` |
| `schedule_at` | |
| `goal_type` | `rebook`, `sell_product`, `awareness` |
| `attribution_days` | |
| `stats_json` | |
| `requires_marketing_opt_in` | default true |

#### 4.4 New: `marketing_campaign_recipients`

Similar to retention cohort members: customer_id, status, sent_at, error, converted_appointment_id.

#### 4.5 New: `auto_tag_rules` (optional P1)

| Field | Notes |
|-------|--------|
| `condition_json` | e.g. spend &gt; X in 90d |
| `tag_id` | apply/remove |
| `is_active` | |

---

### 5. Role impact

| Role | Create segments | Launch campaigns | Manage templates | Auto-tag rules | View campaign ROI |
|------|-----------------|------------------|------------------|----------------|-------------------|
| **Franchise Owner** | Yes | Yes | Yes | Yes | Yes |
| **Franchise Manager** | Yes | Yes | Yes | Yes | Yes |
| **Branch Manager** | Branch-filtered segments | If allowed | No | No | Branch |
| **Staff** | Apply tags only | No | No | No | No |
| **Platform Admin** | — | Abuse limits | Platform template gallery | — | Aggregate |

Permissions: `marketing.view`, `marketing.manage`, `marketing.send`.

---

### 6. Business logic

#### 6.1 Segment rule DSL (v1 — kept simple)

JSON AND/OR groups with operators:

| Dimension | Operators | Example |
|-----------|-----------|---------|
| Tag | has / has_not | VIP |
| Lapse status | in | at_risk, lapsed |
| Last visit days | gt / lt | &gt; 45 |
| Lifetime spend | between | 500–5000 QAR |
| Visit count | gte | ≥ 3 |
| Branch | eq | Branch A |
| Favorite service category | eq | Hair |
| Birthday month | eq | current month |
| Consent | channel opted_in | WhatsApp marketing |

Dynamic segments re-evaluate at send time (not only at save).

#### 6.2 Campaign send pipeline

1. Validate channel connected + template approved.  
2. Resolve segment → recipient list.  
3. Filter opt-in + suppress cooldown + suppress recent campaign overlap.  
4. Enqueue messages with quiet hours.  
5. Update delivery statuses via webhooks/provider.  
6. Attribution: booking/purchase within `attribution_days`.

#### 6.3 Relationship to win-back (05)

Win-back is a **specialized campaign type** pre-wired to lapse statuses. Marketing automation is the **generic engine**. Implementation tip: build shared `OutboundCampaign` core; win-back becomes a preset.

#### 6.4 Auto-tag examples

- Spend &gt; 2000 in 90 days → tag `VIP`.  
- 2+ no-shows in 90 days → tag `No-show risk` (feeds doc 13).  
- Purchased product brand X → tag for retail promo.

#### 6.5 Guardrails

- Max recipients per day by plan.  
- Platform Admin can pause a salon’s marketing on spam complaints.  
- Always store unsubscribe events.

---

### 7. Dependencies

- Hard for WhatsApp channel: doc 01.  
- Soft: docs 05, 18 for richer rules.  
- SMS/email can launch earlier.

---

### 8. Phasing

| Phase | Scope |
|-------|--------|
| P0 | Dynamic segments (tag + last visit + spend) + SMS/email campaign |
| P1 | Auto-tag rules + stats |
| P2 | WhatsApp + shared engine with win-back |
| P3 | Visual journey builder (multi-step) |

---

### 9. Open decisions

1. Build generic engine first vs win-back-only first.  
2. Branch Manager send rights.  
3. Whether static CSV upload segments are allowed (compliance).  

---

### 10. Acceptance criteria

- Changing a customer’s tags updates dynamic segment membership on next campaign resolve.  
- Campaign cannot send to marketing opt-outs.  
- ROI widget counts only attributed appointments in window.  
- Staff can tag but cannot blast campaigns.

---

## 08 — Branch Benchmarking

**Category:** Multi-branch  
**Status:** Partial  
**Priority:** Medium  
**Source:** Strategy doc; Saloenza §4.11  
**As of:** 21 September 2026

---

### 1. Summary

Per-branch reporting and branch-scoped operations already exist. Missing is a **comparative cross-branch view**: revenue, profitability, staff productivity side-by-side for franchise owners.

---

### 2. Current state

- `saloon_branches` multi-branch.  
- Analytics endpoints with branch filters.  
- Branch Manager scoped to one branch.  
- No benchmark matrix / ranking / variance view.

---

### 3. Change level

| Layer | Intensity |
|-------|-----------|
| Database | Low (optional materialized daily branch KPIs) |
| Query/API | Medium |
| UI | Medium–High (comparison table + charts) |
| Permissions | Low–Medium |
| Plan | Multi-branch / Enterprise story |

Mostly **presentation + aggregation** on existing appointments, expenses, inventory COGS if available.

---

### 4. Field & entity changes

#### 4.1 Optional: `branch_kpi_daily`

| Field | Notes |
|-------|--------|
| `saloon_id`, `branch_id`, `date` | |
| `revenue_services`, `revenue_products` | |
| `discount_total`, `net_revenue` | |
| `appointments_completed`, `no_shows`, `cancellations` | |
| `unique_customers` | |
| `new_customers` | first visit |
| `staff_hours_scheduled` | from weekly_schedule approx |
| `staff_hours_served` | sum service durations |
| `cogs_products` | from sale movements × cost |
| `expenses_total` | from expenses |
| `gross_profit_est` | net_revenue - cogs - expenses (define clearly) |
| `avg_ticket` | |

#### 4.2 Settings

| Field | Notes |
|-------|--------|
| `benchmark_profit_formula` | document which costs included |
| `benchmark_peer_mode` | vs salon average / vs best branch |

No changes required to appointment schema.

---

### 5. Role impact

| Role | View all-branch matrix | View own branch vs average | Export | Configure formula |
|------|------------------------|----------------------------|--------|-------------------|
| **Franchise Owner** | Yes | Yes | Yes | Yes |
| **Franchise Manager** | Yes | Yes | Yes | Optional |
| **Branch Manager** | **No** peer revenue of others (policy) OR anonymized rank only | Yes vs average (optional) | Own | No |
| **Staff** | No | No | No | No |
| **Platform Admin** | Cross-tenant only if product analytics | — | — | Defaults |

**Sensitive:** Showing Branch A’s full P&L to Branch B’s manager is often undesirable. Default: **Owner/Franchise Manager only** for full matrix; Branch Manager sees own KPIs + optional “you are 12% below salon avg” without naming peers.

Permissions: reuse `analytics.view`; add `analytics.benchmark.view`.

---

### 6. Business logic

#### 6.1 Core comparison metrics

| KPI | Definition |
|-----|------------|
| Revenue | Sum `grand_total` or paid amounts for completed (policy) |
| Profitability (est.) | Revenue − product COGS − branch expenses (salary estimate optional) |
| Utilization | Served minutes / scheduled minutes |
| No-show rate | no-shows / (completed + no-shows + cancelled?) — define denominator |
| New customer mix | first-time / total unique |
| Retail attach | product revenue / service revenue |
| Staff productivity | revenue per stylist or per served hour |

#### 6.2 Views

1. **Matrix:** branches × KPIs for date range.  
2. **Rank:** sort by selected KPI.  
3. **Variance:** % vs salon average and vs prior period.  
4. **Staff comparison across branches:** only Owner — revenue/commissionable sales per staff (careful with privacy).

#### 6.3 Aggregation job

Nightly fill `branch_kpi_daily`; API sums range. On-demand recompute for short ranges if needed.

#### 6.4 Fairness rules

- Exclude `product_sale`-only days consistently.  
- Timezone: use salon/branch local dates.  
- Inactive branches hidden by default.

---

### 7. Dependencies

- Multi-branch entitlement.  
- Expense categorization quality.  
- Stronger profit story if inventory costs maintained.  
- Complements AI insights (02) and dynamic pricing (20).

---

### 8. Phasing

| Phase | Scope |
|-------|--------|
| P0 | Revenue + appointments + no-show matrix for Owner |
| P1 | Expenses + estimated profit + utilization |
| P2 | Staff cross-branch comparison + export |
| P3 | Anonymized peer view for Branch Managers |

---

### 9. Open decisions

1. Revenue on **completed** vs **paid**.  
2. Include `per_month_salary` allocated to branch in profit?  
3. Branch Manager peer visibility.  
4. Whether franchise “HQ” virtual branch exists.

---

### 10. Acceptance criteria

- Two-branch salon sees side-by-side revenue matching single-branch reports summed.  
- Branch Manager cannot open another branch’s customer-level drill-down.  
- Date range and branch filters are consistent with existing analytics.

---

## 09 — Advanced Payroll

**Category:** Staff  
**Status:** Missing (Appendix C — out of scope)  
**Priority:** Low  
**Source:** Saloenza Appendix C  
**As of:** 21 September 2026

---

### 1. Summary

Appendix C excludes full payroll, attendance, and leave. Today only `users.per_month_salary` and expense category “salary” support rough P&L. This doc plans what **advanced payroll** would mean if product strategy later expands — typically after commission engine (04) and attendance (19).

**GTM advice:** Keep out of scope for Qatar salon MVP; sell commissions + exports instead of full payroll compliance.

---

### 2. Current state

| Exists | Missing |
|--------|---------|
| `per_month_salary` | Pay runs, deductions, statutory |
| Staff earnings (flat commission) | Net pay calculation |
| Expenses | Payslips, bank files |
| `weekly_schedule` | Attendance-linked pay |

---

### 3. Change level (if approved)

| Layer | Intensity |
|-------|-----------|
| Database | Very High |
| Compliance (Qatar Labor) | Very High — legal review required |
| UI | High |
| Integrations | Medium (banks, WPS if applicable) |
| Dependencies | Attendance (19), commissions (04) |

**Engineering + legal size:** Very large. Not a “report feature.”

---

### 4. Field & entity changes (conceptual)

#### 4.1 Employee payroll profile (extend user or `staff_payroll_profiles`)

| Field | Notes |
|-------|--------|
| `basic_salary` | Replace/align `per_month_salary` |
| `housing_allowance`, `other_allowances` | Qatar-common components |
| `iban` / bank details encrypted | |
| `qid_number` / labor identifiers | Sensitive |
| `pay_frequency` | monthly |
| `hire_date`, `end_date` | |
| `tax_treatments` | as applicable |

#### 4.2 `pay_runs`

| Field | Notes |
|-------|--------|
| `saloon_id`, `period_start/end` | |
| `status` | `draft`, `calculated`, `approved`, `paid`, `void` |
| `approved_by`, `paid_at` | |

#### 4.3 `pay_run_lines`

| Field | Notes |
|-------|--------|
| `user_id` | |
| `earnings_json` | basic, allowances, commission total, overtime, tips |
| `deductions_json` | advances, penalties, absences |
| `gross`, `net` | |
| `commission_payout_period_id` | link to doc 04 |
| `attendance_summary_json` | from doc 19 |

#### 4.4 Leave & adjustments (minimal)

`leave_requests`, `salary_advances`, `payroll_adjustments` — each with approval workflow.

---

### 5. Role impact

| Role | Configure pay components | Run payroll | Approve | Mark paid | View own payslip | View others |
|------|--------------------------|-------------|---------|-----------|------------------|-------------|
| **Franchise Owner** | Yes | Yes | Yes | Yes | Yes | Yes |
| **Franchise Manager** | Limited | Yes | Optional | No | Yes | Yes if permitted |
| **Branch Manager** | No | No | Attendance inputs only | No | Yes | Branch staff summary only |
| **Staff** | No | No | No | No | Own | No |
| **Platform Admin** | Feature flag | No tenant employee data access by default | | | | |

Permissions: `payroll.view`, `payroll.manage`, `payroll.approve` — highly restricted.

---

### 6. Business logic (simplified)

1. Period opens → pull locked commission totals (04) + attendance (19) + fixed salary components.  
2. Apply absence deductions per policy (e.g. daily rate = basic/30).  
3. Apply advances/deductions.  
4. Calculate gross/net.  
5. Owner approves → lock.  
6. Export CSV/bank file; mark paid.  
7. Generate PDF payslip.

**Statutory rules:** Must be validated by Qatar employment counsel (WPS, end-of-service gratuity, overtime). Do not invent compliance logic in engineering alone.

---

### 7. Decision: stay out of scope?

| Stay out | Enter scope |
|----------|-------------|
| Export commission + salary fields to Excel | Full pay runs |
| Expense “salary” for P&L | Legal-compliant payroll |
| Lower support burden | High support + liability |

**Recommendation:** Remain Appendix C excluded; offer **payroll-ready exports** as a thin bridge.

---

### 8. Thin alternative (suggested instead)

“Payroll-ready export” (Small change):

- CSV: staff name, branch, basic salary, commission locked total, advances (manual column), period dates.  
- No net pay calculation claimed.

---

### 9. Dependencies

- Doc 04 commission periods.  
- Doc 19 attendance for overtime/absence.  
- Encryption/KMS for bank details.

---

### 10. Open decisions

1. Target Qatar WPS compliance or generic payslips only?  
2. Multi-country later?  
3. Who is data controller for QID/bank data?

---

### 11. Acceptance criteria (only if built)

- Approved pay run immutable.  
- Staff see only own slips.  
- Commission totals match locked commission periods.  
- Legal checklist signed before marketing as “payroll.”

---

## 10 — API / Third-Party Integrations

**Category:** Platform  
**Status:** Missing (as productized public integrations)  
**Priority:** Medium  
**Source:** Strategy doc  
**As of:** 21 September 2026

---

### 1. Summary

Saloenza has an **internal REST API** for its SPA (`/api/v1/...`), subscription payments (Razorpay/Stripe), and SMS providers. What strategy docs usually mean by “API / third-party integrations” is a **productized** layer: public API keys for salons, webhooks, and named integrations (accounting, WhatsApp BSP, Google, Instagram, payment terminals, etc.).

---

### 2. Current state

| Exists | Gap |
|--------|-----|
| Sanctum-authenticated API for app users | Salon developer API keys / OAuth apps |
| SMS gateway configs | Pluggable integration catalog UI |
| Stripe/Razorpay for **subscriptions** | Client-facing payment terminal integrations |
| No public webhook story | Outbound webhooks to salon systems |
| Appendix C: no hardware terminals | Still a common sales ask |

---

### 3. Change level

| Layer | Intensity |
|-------|-----------|
| Auth / API tokens | High |
| Webhooks | High |
| Developer portal UI | Medium |
| Integration connectors | High each |
| Security / rate limits | High |
| Docs | High |

---

### 4. Field & entity changes

#### 4.1 `api_clients` (per salon)

| Field | Notes |
|-------|--------|
| `saloon_id` | |
| `name` | “Zapier”, “Custom ERP” |
| `key_prefix`, `key_hash` | Show secret once |
| `scopes` | JSON list |
| `is_active` | |
| `last_used_at` | |
| `created_by` | |
| `rate_limit_per_min` | |

#### 4.2 `webhook_endpoints`

| Field | Notes |
|-------|--------|
| `saloon_id` | |
| `url` | HTTPS |
| `secret` | signing |
| `events` | array |
| `is_active` | |
| `failure_count`, `last_status` | |

#### 4.3 `webhook_deliveries`

| Field | Notes |
|-------|--------|
| `event_type`, `payload` | |
| `response_code`, `attempts` | |
| `status` | `pending`, `success`, `failed` |

#### 4.4 `integration_installations`

| Field | Notes |
|-------|--------|
| `saloon_id` | |
| `provider` | `whatsapp_meta`, `google_reserve`, `quickbooks`, `instagram`, … |
| `config_encrypted` | |
| `status` | |
| `installed_by` | |

#### 4.5 Scope vocabulary (examples)

`appointments.read`, `appointments.write`, `customers.read`, `customers.write`, `catalog.read`, `inventory.read`, `webhooks.manage`.

---

### 5. Role impact

| Role | Create API keys | Configure webhooks | Install integrations | Use API | Rotate/revoke |
|------|-----------------|--------------------|----------------------|---------|---------------|
| **Franchise Owner** | Yes | Yes | Yes | Via systems | Yes |
| **Franchise Manager** | Optional | Optional | Optional | | Yes if permitted |
| **Branch Manager** | No | No | No | No | No |
| **Staff** | No | No | No | No | No |
| **Platform Admin** | Platform connectors | Force revoke | Manage OAuth apps | Platform | Yes |
| **External developer** | — | — | — | With key + scopes | — |

UI: Settings → **Developers** (Enterprise) + **Integrations** marketplace.

Plan gate: Enterprise or add-on.

---

### 6. Business logic

#### 6.1 API key auth

- Pass `Authorization: Bearer sk_live_...`  
- Resolve salon tenant; enforce scopes; rate limit; audit log.  
- Never allow platform-admin scopes via salon keys.

#### 6.2 Webhooks

Emit on: appointment created/updated/completed/cancelled/no-show, customer created, inventory low stock, invoice paid.

Sign with HMAC SHA-256; retry with backoff; disable after N failures; allow manual replay.

#### 6.3 Integration connector pattern

Each connector: OAuth or API key → map to Saloenza domain events → field mapping → sync job.

Priority connectors for Qatar GTM:

1. WhatsApp BSP (doc 01)  
2. Google Booking / Reserve (doc 16)  
3. Accounting export (QuickBooks/Xero) — outbound invoices  
4. Instagram — later (doc 16)  
5. Payment links / card-on-file provider (doc 13)

#### 6.4 Safety

- Idempotency keys on writes.  
- PII minimization in webhook payloads (settings).  
- IP allowlist optional.

---

### 7. Dependencies

- Stable public resource shapes (versioned `/api/public/v1`).  
- WhatsApp, Google, payments features as separate projects.  
- Support playbook for broken webhooks.

---

### 8. Phasing

| Phase | Scope |
|-------|--------|
| P0 | API keys + read-only appointments/customers + docs |
| P1 | Webhooks + write appointments (limited) |
| P2 | Integrations catalog (WhatsApp, accounting CSV/API) |
| P3 | OAuth partner apps |

---

### 9. Open decisions

1. Expose API on Pro or Enterprise only?  
2. Allow write APIs early or read-only first?  
3. Partner program for certified integrators?  
4. SLA for webhook delivery?

---

### 10. Acceptance criteria

- Owner can create a key, call list appointments, revoke key (401 thereafter).  
- Webhook signature verified in sample docs.  
- Scoped key cannot access other salons.  
- Rate limit returns clear 429.

---

## 11 — Productized Free Migration + Onboarding + Training Guarantee

**Category:** Positioning / GTM  
**Status:** Partial  
**Priority:** Low (engineering) / High (sales packaging)  
**Source:** Strategy doc  
**As of:** 21 September 2026

---

### 1. Summary

Saloenza already has **self-serve onboarding** and **admin-assisted onboarding**. Strategy wants this packaged as a marketed **“free migration + onboarding + training” guarantee** and often paired with **0% commission on payment processing** positioning (vs POS competitors that take a cut).

This is mostly **process, CRM fields, SLAs, and marketing copy** — light engineering.

---

### 2. Current state

| Exists | Gap |
|--------|-----|
| Onboarding wizard | Marketed “switch guarantee” SKU |
| Admin onboarding APIs | Migration checklist / status tracker visible to salon |
| Plans Free→Enterprise | Explicit “we migrate your data free” workflow |
| Subscription billing | “0% commission” messaging for client payments (Saloenza doesn’t take cut of salon-client charges today) |

---

### 3. Change level

| Layer | Intensity |
|-------|-----------|
| Engineering | Low–Medium |
| Ops playbooks | High |
| Marketing site / sales decks | High |
| Admin UI | Low–Medium |
| Legal (guarantee terms) | Medium |

---

### 4. Field & entity changes

#### 4.1 Extend salon / onboarding record

| Field | Notes |
|-------|--------|
| `migration_status` | `not_requested`, `queued`, `in_progress`, `completed`, `waived` |
| `migration_source` | `excel`, `fresha`, `other_saas`, `paper` |
| `migration_due_at` | SLA date |
| `onboarding_package` | `standard`, `switch_guarantee` |
| `training_sessions_included` | int |
| `training_sessions_done` | int |
| `success_manager_admin_id` | platform user |
| `go_live_at` | |
| `guarantee_notes` | internal |

#### 4.2 New: `onboarding_tasks` (checklist)

| Field | Notes |
|-------|--------|
| `saloon_id` | |
| `code` | `import_customers`, `import_services`, `setup_staff`, `connect_whatsapp`, `trial_bookings`, `training_1`, … |
| `owner_type` | `salon`, `saloenza` |
| `status` | `todo`, `doing`, `done`, `blocked` |
| `due_at`, `completed_at` | |
| `evidence_url` / notes | |

#### 4.3 New: `data_import_jobs` (light)

| Field | Notes |
|-------|--------|
| `type` | customers/services/products |
| `file_path`, `status`, `error_report` | |
| `imported_counts` | |

#### 4.4 Plan / pricing metadata

| Field | Notes |
|-------|--------|
| Plan flag `includes_switch_guarantee` | Pro/Enterprise |
| Marketing flag `zero_commission_client_payments` | true — clarify means Saloenza fee model |

---

### 5. Role impact

| Role | Request migration | Upload sheets | See checklist | Complete Saloenza tasks | Mark trained / go-live | Edit guarantee terms |
|------|-------------------|---------------|---------------|-------------------------|------------------------|----------------------|
| **Salon Owner** | Yes | Yes | Yes | Salon-owned tasks | Confirm go-live | No |
| **Franchise Manager** | Yes | Yes | Yes | Yes | Optional | No |
| **Branch Manager** | No | Branch data assist | View | Limited | No | No |
| **Staff** | No | No | No | Attend training | No | No |
| **Platform Super Admin** | Create package | Run import | Yes | Yes | Yes | Yes |
| **Affiliate** | May refer; no access to checklist | | | | | |

UI:

- Salon: **Getting started** checklist with % complete.  
- Admin: **Onboarding pipeline** board (queued → in progress → live).

---

### 6. Business logic / ops SLA

#### 6.1 Switch guarantee definition (draft for legal)

Within X days of Pro subscription:

1. Import customers + services from Excel template **or** assisted extract.  
2. Configure one branch calendar + staff schedules.  
3. One live training session (remote).  
4. First week hypercare channel.

If Saloenza misses SLA → remedy (extend subscription N days) — **legal to finalize**.

#### 6.2 Workflow

1. Sales marks `onboarding_package=switch_guarantee`.  
2. System creates task list from template.  
3. Admin assigned as success manager.  
4. Salon uploads CSV; validation errors returned as report.  
5. Admin imports; salon verifies sample.  
6. Training scheduled; `training_sessions_done++`.  
7. Go-live → `migration_status=completed`; checklist archived.

#### 6.3 0% commission positioning

**Truth in advertising rules:**

- Saloenza subscription is SaaS fee; it does **not** take % of salon↔client service payments today.  
- Payment gateway fees (Stripe/Razorpay/bank) may still apply if card products added (doc 13).  
- Marketing must say **“No Saloenza commission on your service sales”**, not “no payment fees ever.”

#### 6.4 What engineering should NOT overbuild

- Full automated Fresha OAuth migrator (unless volume demands).  
- Excel templates + admin tooling covers 80%.

---

### 7. Dependencies

- Clear Excel templates (customers, services, staff).  
- Support staffing capacity.  
- Optional WhatsApp connection as training milestone (01).

---

### 8. Phasing

| Phase | Scope |
|-------|--------|
| P0 | Marketing copy + ops playbook + admin tags/fields (minimal) |
| P1 | In-app checklist visible to Owner |
| P2 | CSV import self-serve with error report |
| P3 | SLA timers + remedy automation |

---

### 9. Open decisions

1. Which plans include the guarantee?  
2. SLA days (7 / 14 / 30)?  
3. Cap on migrated customer rows?  
4. Exact legal wording for 0% commission.

---

### 10. Acceptance criteria

- Admin can move a salon through migration statuses.  
- Owner sees outstanding onboarding tasks.  
- Sales deck bullets match in-app package flags.  
- No false claim of zero **gateway** fees if card capture exists.

---

## 12 — Arabic / Bilingual UI and Receipts

**Category:** Localization (Qatar market)  
**Status:** Suggested (beyond both docs)  
**Priority:** High  
**As of:** 21 September 2026

---

### 1. Summary

For Qatar GTM, Arabic (or bilingual EN/AR) UI and receipts are near-essential. This is not in the strategy-vs-feature gap table as a Saloenza “module,” but it affects **every screen, PDF, and message template**.

---

### 2. Current state

- Product UI/docs primarily English.  
- Receipts as PDF from appointments.  
- Notification templates in email/SMS (language handling unclear / EN-centric).  
- No first-class `locale` / RTL layout system documented as complete.

---

### 3. Change level

| Layer | Intensity |
|-------|-----------|
| Frontend i18n + RTL | Very High |
| PDF receipts bilingual | High |
| Message templates AR | High (ties to WhatsApp 01) |
| Backend locale fields | Medium |
| Catalog translation | Medium–High |
| Training / support | Medium |

**Note:** This touches almost all SPA views — treat as a program, not a feature toggle alone.

---

### 4. Field & entity changes

#### 4.1 User / salon preferences

| Location | Field | Notes |
|----------|-------|--------|
| `users` | `preferred_locale` | `en`, `ar` |
| `saloons` / settings | `default_locale` | |
| `saloons` / settings | `receipt_language` | `en`, `ar`, `bilingual` |
| `saloons` / settings | `ui_force_rtl` | derived from locale |

#### 4.2 Translatable content

| Entity | Approach |
|--------|----------|
| Services, products, categories | `name_ar`, `description_ar` **or** `translations` JSON |
| Customer-facing booking link | Locale from link param or salon default |
| Expense categories / system enums | i18n catalogs in frontend |
| Roles / permissions labels | i18n |

#### 4.3 Receipt / invoice

| Field | Notes |
|-------|--------|
| Store snapshot | `receipt_locale` on appointment at issue time |
| Business legal name AR | `trade_name_ar` on salon |
| Address fields AR | optional parallel fields |

#### 4.4 Notifications

Template rows already proposed in WhatsApp doc: `language=ar` variants for every transactional template.

---

### 5. Role impact

| Role | Switch own UI language | Set salon default | Edit AR catalog names | Issue AR/bilingual receipt | Approve AR message templates |
|------|------------------------|-------------------|-----------------------|----------------------------|------------------------------|
| **Franchise Owner** | Yes | Yes | Yes | Yes | Yes |
| **Franchise Manager** | Yes | Yes | Yes | Yes | Yes |
| **Branch Manager** | Yes | No | Branch catalog if allowed | Yes | No |
| **Staff** | Yes (personal) | No | No | Uses salon receipt setting | No |
| **Customer** | Booking page language toggle | — | — | Receives configured receipt | — |
| **Platform Admin** | Yes | Platform defaults | Master catalog AR | — | Platform template gallery |

---

### 6. Business logic

1. **Resolution order for UI:** user preferred_locale → salon default → `en`.  
2. **RTL:** when locale is `ar`, enable RTL layout (mirroring nav, calendars carefully).  
3. **Catalog fallback:** if `name_ar` empty, show `name` (EN) rather than blank.  
4. **Receipts:**  
   - `en` / `ar` single language, or  
   - `bilingual` stacked blocks (AR right-to-left section + EN).  
5. **Numbers/dates:** use Qatar-friendly formats; currency QAR labeling.  
6. **Public booking:** `?lang=ar` persists for session.  
7. **Staff mixed preference:** UI in EN while receipt AR is allowed (common).  
8. **Search:** Arabic-aware collation where possible; keep SKU/code language-neutral.

---

### 7. Dependencies

- Design system capable of RTL.  
- Translation process (professional AR for beauty domain terms).  
- WhatsApp/SMS template approvals in Arabic (01).  
- PDF font embedding with Arabic shaping support.

---

### 8. Phasing

| Phase | Scope |
|-------|--------|
| P0 | SPA i18n framework + AR strings for top 20 screens + RTL shell |
| P1 | Public booking AR + bilingual PDF receipt |
| P2 | Catalog AR fields + notifications AR |
| P3 | Full coverage + admin translation QA tool |

---

### 9. Open decisions

1. Full AR UI vs customer-facing only (booking + receipts)? **Recommend full UI for Qatar.**  
2. Store bilingual catalog vs single primary + translations table.  
3. Who pays for professional translation updates each release?

---

### 10. Acceptance criteria

- Owner can set salon default to Arabic; Staff see RTL UI.  
- Receipt PDF renders Arabic correctly (no reversed glyphs).  
- Booking link in AR completes an appointment successfully.  
- Missing AR translations fall back to English without breaking layout.

---

## 13 — No-Show Protection (Card-on-File / Prepayment)

**Category:** Bookings / Revenue protection  
**Status:** Suggested  
**Priority:** Medium  
**As of:** 21 September 2026

---

### 1. Summary

Appointments already support status `no-show` and payment fields, but there is **no deposit, prepayment, or card-on-file fee** automation for repeat no-show clients. This feature reduces revenue loss and pairs with Intelligence no-show insights (02).

---

### 2. Current state

- Status `no-show` + dashboard counts.  
- Payment: `payment_status`, `payment_method`, `amount_paid` on appointment.  
- No payment-method-on-file for clients.  
- No automatic fee on no-show.  
- Appendix C: no hardware terminals — **online payment links / card vaulting** still possible via Stripe/similar.

---

### 3. Change level

| Layer | Intensity |
|-------|-----------|
| Payments integration (client charges) | High |
| Database | Medium–High |
| Booking + POS flows | High |
| Policies / UI | Medium |
| Legal / customer communication | Medium |
| Permissions | Medium |

---

### 4. Field & entity changes

#### 4.1 Salon policy: `no_show_policies`

| Field | Notes |
|-------|--------|
| `saloon_id` | |
| `is_enabled` | |
| `apply_mode` | `all_clients`, `tagged_only`, `repeat_offenders`, `high_risk` |
| `min_no_shows` | e.g. 2 in 90 days |
| `window_days` | 90 |
| `protection_type` | `deposit`, `full_prepay`, `card_on_file_auth` |
| `deposit_type` | `fixed`, `percent` |
| `deposit_value` | amount or % |
| `fee_on_no_show` | fixed/percent of service |
| `cancel_cutoff_hours` | free cancel before X hours |
| `currency` | |

#### 4.2 Customer risk

| Field | Notes |
|-------|--------|
| `no_show_count_90d` | computed |
| `require_prepayment` | bool override |
| `card_on_file_status` | `none`, `saved`, `expired` |

#### 4.3 `customer_payment_methods` (vault references — never store raw PAN)

| Field | Notes |
|-------|--------|
| `customer_id`, `saloon_id` | |
| `provider` | `stripe`, … |
| `provider_customer_id` | |
| `provider_payment_method_id` | |
| `brand`, `last4`, `exp` | display |
| `is_default` | |

#### 4.4 Appointment extensions

| Field | Notes |
|-------|--------|
| `deposit_required_amount` | |
| `deposit_status` | `not_required`, `pending`, `paid`, `waived` |
| `deposit_paid_at` | |
| `prepayment_payment_id` | FK |
| `no_show_fee_amount` | |
| `no_show_fee_status` | `n/a`, `pending`, `charged`, `failed`, `waived` |

#### 4.5 New: `client_payment_intents` / transactions

| Field | Notes |
|-------|--------|
| `type` | `deposit`, `prepay`, `no_show_fee`, `refund` |
| `appointment_id`, `customer_id` | |
| `amount`, `status` | |
| `provider_ref` | |

**Also recommended:** split tender table (see loyalty doc) if deposits coexist with cash checkout.

---

### 5. Role impact

| Role | Configure policy | Force require deposit | Waive fee | Charge no-show fee | View card last4 | Refund deposit |
|------|------------------|----------------------|-----------|--------------------|-----------------|---------------|
| **Franchise Owner** | Yes | Yes | Yes | Yes | Yes | Yes |
| **Franchise Manager** | Yes | Yes | Yes | Yes | Yes | Yes |
| **Branch Manager** | No | Yes on booking | Limited | Yes | Yes | Limited |
| **Staff** | No | Follow system prompt | No | No | Last4 only | No |
| **Customer** | — | Pay deposit link | — | Receives notice | Manage cards if portal | — |
| **Platform Admin** | Provider keys | — | — | — | No card data | Support |

Permissions: `payments.policy.manage`, `payments.charge`, `payments.refund`, `payments.waive`.

---

### 6. Business logic

#### 6.1 When protection applies

At booking create/confirm:

1. Evaluate policy `apply_mode`.  
2. If applies → set `deposit_required_amount` / require card save.  
3. Public booking: block confirm until deposit paid or card saved (per type).  
4. Internal booking: allow `pending` with staff warning; optional hold slot.

#### 6.2 Cancel rules

- Cancel ≥ cutoff → refund deposit / release auth.  
- Cancel &lt; cutoff → retain deposit or partial (policy).  
- Reschedule within rules → carry deposit to new appointment.

#### 6.3 No-show charging

On status → `no-show`:

1. If deposit paid → convert deposit to fee (or keep and charge delta).  
2. If card_on_file → create charge `no_show_fee`.  
3. Failures → flag customer `require_prepayment=true` + notify manager.  
4. Notify customer (SMS/WA/email) with receipt.

#### 6.4 Repeat offender tagging

Nightly: if no-shows ≥ threshold → tag `No-show risk` + set `require_prepayment`.

#### 6.5 Accounting

Deposits are liabilities until visit completed → then recognize as revenue (finance policy).

---

### 7. Dependencies

- Payment provider supporting save card + off-session charge.  
- Customer phone/email for payment links.  
- Clear policies shown at booking (AR/EN — doc 12).  
- Not the same as Saloenza subscription Stripe/Razorpay.

---

### 8. Phasing

| Phase | Scope |
|-------|--------|
| P0 | Policy + deposit via payment link + manual mark paid |
| P1 | Auto fee on no-show with card-on-file |
| P2 | Risk-based auto apply + Intelligence CTA |
| P3 | Partial auth / tip / multi-currency |

---

### 9. Open decisions

1. Provider for Qatar (Stripe availability vs local acquirer).  
2. Apply to self-booking only or also walk-in bookings?  
3. Staff override ethics (waive too easily).  
4. Legal disclosure language.

---

### 10. Acceptance criteria

- High-risk customer cannot complete online booking without deposit when policy on.  
- No-show triggers fee attempt and auditable payment row.  
- Waive requires manager permission and reason.  
- Completed visit with deposit correctly reduces balance due at POS.

---

## 14 — Two-Way WhatsApp Concierge

**Category:** Communication  
**Status:** Suggested  
**Priority:** Medium  
**As of:** 21 September 2026

---

### 1. Summary

Builds on WhatsApp automation (01). Instead of one-way blasts, clients can **reply** to reschedule/cancel/confirm, and staff get an inbox-style workflow. This is a distinct product surface: **conversation state machine + staff inbox**.

---

### 2. Current state

- No inbound WhatsApp handling.  
- Cancellations/reschedules via staff UI or customer not doing self-service beyond initial public book.  
- Message log proposed in doc 01 becomes the foundation.

---

### 3. Change level

| Layer | Intensity |
|-------|-----------|
| Depends on 01 | Hard dependency |
| Inbound webhooks | High |
| Conversation / intent engine | High |
| Staff inbox UI | High |
| Appointment mutate APIs | Medium (reuse) |
| Permissions | Medium |

---

### 4. Field & entity changes

#### 4.1 Reuse `whatsapp_messages`; add conversations

##### `whatsapp_conversations`

| Field | Notes |
|-------|--------|
| `saloon_id`, `branch_id` nullable | |
| `customer_id` | |
| `provider_conversation_key` | phone-based |
| `status` | `bot_active`, `awaiting_staff`, `closed` |
| `assigned_user_id` | staff |
| `last_message_at` | |
| `linked_appointment_id` | context |

##### `whatsapp_conversation_events`

| Field | Notes |
|-------|--------|
| `type` | `intent_detected`, `slot_offered`, `staff_takeover`, … |
| `payload_json` | |

#### 4.2 Concierge settings

| Field | Notes |
|-------|--------|
| `concierge_enabled` | |
| `allowed_intents` | confirm, cancel, reschedule, FAQ |
| `cancel_cutoff_hours` | align with doc 13 |
| `auto_reply_outside_hours` | text |
| `staff_handoff_keywords` | “agent”, Arabic equivalents |

#### 4.3 Appointment link tokens for deep actions

Optional short codes in messages: reply `1` confirm, `2` cancel — store `pending_action` on conversation.

---

### 5. Role impact

| Role | Enable concierge | Handle inbox | Take over bot | Confirm bot cancel/reschedule rules | View all transcripts |
|------|------------------|--------------|---------------|-------------------------------------|----------------------|
| **Franchise Owner** | Yes | Yes | Yes | Yes | Yes |
| **Franchise Manager** | Yes | Yes | Yes | Yes | Yes |
| **Branch Manager** | No | Own branch inbox | Yes | Limited | Branch |
| **Staff / Reception** | No | Assigned / branch queue | Yes | No | Own chats |
| **Customer** | Reply on WA | — | Ask for human | — | — |
| **Platform Admin** | Kill switch | — | — | Template policies | Support with consent |

Permissions: `whatsapp.view_inbox`, `whatsapp.manage_concierge`, `whatsapp.takeover`.

---

### 6. Business logic

#### 6.1 Session window

Meta policies: free-form replies only within 24h customer-care window; outside window only templates. Concierge must track window expiry.

#### 6.2 Intent flows (v1 keyword / button)

| Intent | Detection | Action |
|--------|-----------|--------|
| Confirm | REPLY YES / button | status → `confirmed` |
| Cancel | CANCEL | if within policy → `cancelled`; else offer staff |
| Reschedule | RESCHEDULE | offer 2–3 slots from availability engine; customer picks; update appointment |
| Human | AGENT | `awaiting_staff`, notify reception |
| Unknown | — | send help menu once; then handoff |

#### 6.3 Safety rails

- Never cancel paid/deposit appointments without policy checks (doc 13).  
- Idempotent: duplicate YES does nothing.  
- Identify customer by phone → salon mapping; handle unknown numbers (create lead or ask identity).  
- Multi-appointment: ask which booking (list next 3).

#### 6.4 Staff inbox

- Queue sorted by waiting time.  
- Suggested replies.  
- One-click: open appointment, reschedule calendar, mark no-show.  
- Closing conversation returns to bot for future templates.

#### 6.5 Languages

Match bilingual templates (12); detect AR replies for keyword sets.

---

### 7. Dependencies

- **Hard:** WhatsApp automation foundation (01).  
- Availability engine (existing staff weekly_schedule).  
- Optional waitlist (15) when cancel frees a slot.

---

### 8. Phasing

| Phase | Scope |
|-------|--------|
| P0 | Inbound log + staff inbox + manual reply |
| P1 | Confirm / cancel quick replies |
| P2 | Reschedule slot picker |
| P3 | NLP / AI assist (optional) |

---

### 9. Open decisions

1. Bot-first vs human-first for VIP tags.  
2. Who gets notified on handoff (role vs named reception user)?  
3. Store full transcripts retention period (privacy).

---

### 10. Acceptance criteria

- Customer reply YES on reminder confirms appointment.  
- Cancel outside cutoff is refused with explanation + handoff option.  
- Staff can takeover and reply in-thread.  
- Outside 24h window, system sends template not free text.

---

## 15 — Waitlist Auto-Fill

**Category:** Bookings  
**Status:** Suggested (not implemented today)  
**Priority:** Medium  
**As of:** 21 September 2026

---

### 1. Summary

When a slot is cancelled (or no-show early enough), automatically offer it to waitlisted clients who want that service/staff/time window. Maximizes utilization; pairs with WhatsApp (01/14).

---

### 2. Current state

- Full appointments calendar; no waitlist entity/API.  
- Cancellations free the slot silently.  
- Public booking creates appointments only when slot free.

---

### 3. Change level

| Layer | Intensity |
|-------|-----------|
| Database | Medium |
| Booking UI | Medium |
| Notification jobs | Medium |
| Rules engine | Medium |
| Permissions | Low |

---

### 4. Field & entity changes

#### 4.1 `waitlist_entries`

| Field | Notes |
|-------|--------|
| `saloon_id`, `branch_id` | |
| `customer_id` | |
| `service_id` (or multi via child) | |
| `preferred_staff_id` nullable | any |
| `earliest_at`, `latest_at` | window |
| `preferred_days` JSON | optional |
| `priority` | VIP / paid boost / FIFO |
| `status` | `waiting`, `offered`, `booked`, `expired`, `cancelled` |
| `notes` | |
| `created_by` | |
| `expires_at` | |

#### 4.2 `waitlist_offers`

| Field | Notes |
|-------|--------|
| `waitlist_entry_id` | |
| `appointment_slot_start/end` | |
| `staff_id`, `service_id` | |
| `status` | `pending`, `accepted`, `declined`, `expired`, `revoked` |
| `offered_at`, `expires_at` | short TTL e.g. 15–30 min |
| `channel` | whatsapp/sms/link |
| `response_appointment_id` | |

#### 4.3 Salon settings

| Field | Notes |
|-------|--------|
| `waitlist_enabled` | |
| `offer_ttl_minutes` | |
| `max_parallel_offers` | usually 1 sequential |
| `auto_fill_on` | `cancel`, `reschedule_release` |

---

### 5. Role impact

| Role | Enable feature | Add to waitlist | Reorder priority | Trigger manual offer | See offers log |
|------|----------------|-----------------|------------------|----------------------|----------------|
| **Franchise Owner** | Yes | Yes | Yes | Yes | Yes |
| **Franchise Manager** | Yes | Yes | Yes | Yes | Yes |
| **Branch Manager** | Branch on/off if allowed | Yes | Limited | Yes | Branch |
| **Staff** | No | Yes | No | Optional | Own |
| **Customer** | Join via booking “notify me” | — | — | Accept/decline link | — |

Permissions: `waitlist.view`, `waitlist.manage`.

---

### 6. Business logic

#### 6.1 Entry creation

- From public booking when no slot: “Join waitlist”.  
- From staff UI while phone call.  
- Require contact channel.

#### 6.2 Match algorithm (on slot release)

When appointment cancelled (or staff opens gap):

1. Find `waiting` entries matching branch + service + time window + staff preference.  
2. Sort by: priority DESC, created_at ASC (FIFO), optional CLV boost (18).  
3. If `max_parallel_offers=1`: offer to #1 only.  
4. Create `waitlist_offers` + send deep link / WA button.  
5. Hold soft reservation (optional) so public booking cannot steal slot until TTL.

#### 6.3 Accept / decline

- Accept → create appointment; entry `booked`; revoke other pending offers for same slot.  
- Decline / expire → offer next candidate.  
- Customer already booked elsewhere → skip.

#### 6.4 Conflicts

- Soft hold recommended to prevent double booking.  
- If hold not used: accept path must re-check availability transactionally.

#### 6.5 Expiry of entries

Nightly expire waitlist rows past `expires_at` or window passed.

---

### 7. Dependencies

- Messaging channel (SMS minimum; WA ideal).  
- Existing availability/conflict checks.  
- Optional: concierge (14) for reply YES.

---

### 8. Phasing

| Phase | Scope |
|-------|--------|
| P0 | Waitlist CRUD + manual “offer slot” |
| P1 | Auto-offer on cancel + SMS link + TTL |
| P2 | Soft holds + WhatsApp buttons + priority rules |
| P3 | Multi-service waitlist |

---

### 9. Open decisions

1. Soft hold vs first-accept-wins without hold.  
2. VIP jump-the-queue allowed?  
3. Cross-branch waitlist?

---

### 10. Acceptance criteria

- Cancelled slot notifies top waitlisted client within job SLA.  
- Two clients cannot book the same offered slot.  
- Expired offers auto-cascade to next entry.  
- Staff can see why someone was skipped (mismatch log).

---

## 16 — Google / Instagram “Book Now” Integration

**Category:** Acquisition / Integrations  
**Status:** Suggested  
**Priority:** Low  
**As of:** 21 September 2026

---

### 1. Summary

Sync external **Book Now** entry points (Google Business Profile / Reserve; Instagram/Facebook booking CTA) into the **same Saloenza calendar** already used by public booking links (`SalonBookingLink`). Avoid double books and fragmented diaries.

---

### 2. Current state

- Public booking via `/book/{token}` with branch-scoped links.  
- `booking_source` includes `self_booking` (extend with `google`, `instagram`).  
- No Google Reserve / Meta scheduling integration.  
- Integrations platform (10) would host connectors.

---

### 3. Change level

| Layer | Intensity |
|-------|-----------|
| Partner API certifications | High (esp. Google) |
| Mapping services/staff | Medium |
| Sync jobs / webhooks | High |
| UI mapping screens | Medium |
| Ongoing compliance | High |

Google partnership timelines can dominate schedule.

---

### 4. Field & entity changes

#### 4.1 `booking_channel_connections`

| Field | Notes |
|-------|--------|
| `saloon_id`, `branch_id` | |
| `channel` | `google`, `instagram`, `facebook` |
| `external_location_id` | |
| `oauth_tokens_encrypted` | |
| `status` | |
| `last_sync_at` | |

#### 4.2 `booking_channel_mappings`

| Field | Notes |
|-------|--------|
| Map external service ID → `service_id` | |
| Map external staff → `user_id` | |
| Duration/price overrides | optional |

#### 4.3 Appointment fields

| Field | Notes |
|-------|--------|
| `booking_source` enum extend | `google`, `instagram`, `facebook` |
| `external_booking_id` | idempotency |
| `external_payload` | optional audit |

#### 4.4 Availability export

Feed busy blocks from Saloenza appointments + weekly_schedule to partner APIs.

---

### 5. Role impact

| Role | Connect Google/IG | Map services/staff | Pause sync | View channel bookings |
|------|-------------------|--------------------|------------|------------------------|
| **Franchise Owner** | Yes | Yes | Yes | Yes |
| **Franchise Manager** | Yes | Yes | Yes | Yes |
| **Branch Manager** | Own branch location | Own mappings | Own | Own |
| **Staff** | No | No | No | See appointments as normal |
| **Platform Admin** | Partner app credentials | Support | Force disconnect | — |
| **Customer** | Books on Google/IG | — | — | — |

---

### 6. Business logic

1. **Outbound availability:** push or pull busy slots frequently (near real-time).  
2. **Inbound booking:** create appointment with conflict check; reject if clash; return error to partner.  
3. **Updates:** cancel/reschedule from either side must sync both ways (define source of truth: **Saloenza**).  
4. **Idempotency:** `external_booking_id` unique per channel.  
5. **Services not mapped:** do not list on channel.  
6. **Deposits (13):** if required, either unsupported on channel or redirect to Saloenza payment link (partner-dependent).

#### Instagram/Meta reality check

Meta “Book Now” often deep-links to an external URL — **phase 0** can simply use existing booking link in IG bio/CTA without full API. Full sync is Google-heavy.

---

### 7. Dependencies

- Stable public booking + availability.  
- Integration framework (10).  
- Business verification on Google.  
- Optional no-show policies may not enforce on all channels equally.

---

### 8. Phasing

| Phase | Scope |
|-------|--------|
| P0 | Documented “use booking link” on IG/Google website URL (no API) |
| P1 | Google Calendar busy export / simple sync (if not Reserve) |
| P2 | Google Reserve / Partner APIs if approved |
| P3 | Meta scheduling APIs if available for region |

---

### 9. Open decisions

1. Pursue Google Partner Program vs link-only?  
2. Multi-branch Google locations mapping.  
3. Who handles customer support for Google-originated bookings?

---

### 10. Acceptance criteria

- Channel-created booking appears in Saloenza calendar with correct source.  
- Slot busy in Saloenza cannot be double-booked from channel.  
- Cancel in Saloenza reflects on channel within SLA (if two-way).  
- Unmapped services never offered externally.

---

## 17 — Review & Reputation Management

**Category:** Marketing / Retention  
**Status:** Suggested  
**Priority:** Low  
**As of:** 21 September 2026

---

### 1. Summary

After a completed visit, automatically request a **Google review** (and optionally collect a private rating inside Saloenza). Helps local SEO for Qatar salons; builds on messaging automation (01) and post-service journey.

---

### 2. Current state

- No review models/APIs.  
- Post-service communication not automated on WhatsApp.  
- Completed appointment status available as trigger.

---

### 3. Change level

| Layer | Intensity |
|-------|-----------|
| Database | Low–Medium |
| Messaging templates | Medium |
| Google link / optional API | Low–Medium |
| UI | Medium |
| Permissions | Low |

---

### 4. Field & entity changes

#### 4.1 Salon settings

| Field | Notes |
|-------|--------|
| `google_place_id` / `google_review_url` | |
| `review_request_enabled` | |
| `review_delay_minutes` | e.g. 120 after complete |
| `review_channel` | wa/sms/email |
| `min_internal_rating_for_public_ask` | e.g. only ask Google if internal ≥ 4 |

#### 4.2 `review_requests`

| Field | Notes |
|-------|--------|
| `saloon_id`, `branch_id`, `customer_id`, `appointment_id` | |
| `status` | `scheduled`, `sent`, `clicked`, `skipped`, `suppressed` |
| `channel` | |
| `sent_at` | |
| `suppress_reason` | opted_out, low_rating, already_asked |

#### 4.3 Optional internal feedback: `service_ratings`

| Field | Notes |
|-------|--------|
| `appointment_id`, `customer_id` | |
| `rating` | 1–5 |
| `comment` | private |
| `staff_user_id` | optional |
| `shared_publicly` | false by default |

---

### 5. Role impact

| Role | Configure Google URL | Enable auto-ask | View ratings | Respond to private feedback | Suppress customer |
|------|----------------------|-----------------|--------------|-----------------------------|-------------------|
| **Franchise Owner** | Yes | Yes | Yes | Yes | Yes |
| **Franchise Manager** | Yes | Yes | Yes | Yes | Yes |
| **Branch Manager** | Branch URL if separate | Branch | Branch | Yes | Branch |
| **Staff** | No | No | Own ratings if enabled | No | No |
| **Customer** | Leave Google review | — | — | Submit private rating | Opt out marketing |
| **Platform Admin** | — | — | — | — | Abuse |

---

### 6. Business logic

#### 6.1 Two-step “gate” pattern (recommended)

1. After complete + delay → send **private** “How was your visit?” (1–5).  
2. If rating ≥ threshold → send Google review link.  
3. If rating ≤ 3 → **do not** send public link; create internal alert for manager follow-up (save reputation).

#### 6.2 Suppressions

- Marketing opt-out.  
- Already requested for appointment.  
- Cooldown per customer (e.g. 60 days).  
- No-show/cancelled never trigger.

#### 6.3 Attribution

Track click on review link (redirect through Saloenza short URL). Cannot always verify Google review posted without API.

#### 6.4 Staff coaching

Aggregate internal ratings per staff for managers (careful with fairness / small sample sizes).

---

### 7. Dependencies

- Messaging (01) or SMS/email.  
- Accurate `completed` status timing.  
- Bilingual templates (12).

---

### 8. Phasing

| Phase | Scope |
|-------|--------|
| P0 | Manual “Send review link” on completed appointment |
| P1 | Auto post-service private rating + Google link gate |
| P2 | Manager alerts for low scores + simple reports |
| P3 | Google API verification if available |

---

### 9. Open decisions

1. Always public ask vs gated by private score? **Recommend gated.**  
2. Show staff individual scores?  
3. Incentives for reviews (compliance with Google policies — usually disallowed for gated incentives).

---

### 10. Acceptance criteria

- Completed visit schedules one request; cancel completion cancels pending.  
- 1-star private rating never gets Google link.  
- Opted-out customers skipped.  
- Branch Manager sees branch feedback only.

---

## 18 — Customer Lifetime Value (CLV) Scoring

**Category:** CRM / Analytics  
**Status:** Suggested  
**Priority:** Medium  
**Depends on:** Win-back (05) for full value  
**As of:** 21 September 2026

---

### 1. Summary

Score customers by value so win-back, marketing, waitlist priority, and Intelligence lists target the right people first. v1 can be **rules-based**; true predictive CLV is optional later.

---

### 2. Current state

- Spend and visits available via appointments but not denormalized.  
- Tags exist but no value score.  
- No prioritization in campaigns.

---

### 3. Change level

| Layer | Intensity |
|-------|-----------|
| Database metrics | Medium |
| Nightly scoring job | Medium |
| CRM UI badges | Low–Medium |
| Downstream consumers | Medium (05, 07, 15, 02) |

---

### 4. Field & entity changes

#### 4.1 `customer_value_stats` (per salon)

| Field | Notes |
|-------|--------|
| `customer_id`, `saloon_id` | |
| `lifetime_spend` | |
| `paid_spend` | if differs |
| `visit_count` | |
| `avg_ticket` | |
| `avg_days_between_visits` | |
| `first_visit_at`, `last_visit_at` | |
| `expected_annual_value` | formula |
| `clv_score` | 0–100 |
| `clv_tier` | `platinum`, `gold`, `silver`, `bronze` |
| `churn_risk_score` | 0–100 optional |
| `computed_at` | |

#### 4.2 Salon scoring settings

| Field | Notes |
|-------|--------|
| Weights for spend / frequency / recency | |
| Tier thresholds | percentiles or absolute |
| `predictive_enabled` | false for v1 |

#### 4.3 Optional tag sync

Auto-tag `VIP` when tier = platinum (via doc 07 auto-tag).

---

### 5. Role impact

| Role | Configure weights/tiers | View CLV on customer | Use CLV sort in campaigns | Export |
|------|-------------------------|----------------------|---------------------------|--------|
| **Franchise Owner** | Yes | Yes | Yes | Yes |
| **Franchise Manager** | Yes | Yes | Yes | Yes |
| **Branch Manager** | No | Yes (branch customers) | If campaign rights | Branch |
| **Staff** | No | Badge only (optional) | No | No |
| **Customer** | No | No | No | No |

---

### 6. Business logic

#### 6.1 v1 score (transparent)

Normalize components 0–100 then weighted sum:

| Component | Idea |
|-----------|------|
| Monetary | lifetime_spend vs salon percentile |
| Frequency | visit_count in last 12 months |
| Recency | inverse of days since last visit |
| Margin optional | prefer high-margin services if COGS known |

```
clv_score = wM*M + wF*F + wR*R
```

#### 6.2 Expected annual value (simple)

```
expected_annual_value = avg_ticket * (365 / max(avg_days_between_visits, 1))
```

Use only if visit_count ≥ 2; else null.

#### 6.3 Churn risk (simple)

High if days_since_visit >> avg_days_between_visits; feeds lapse_status (05).

#### 6.4 Tiers

Either fixed cutoffs (spend-based) or quintiles per salon monthly.

#### 6.5 Recompute

Nightly + on appointment completed webhook.

#### 6.6 Usage

- Win-back cohort sort (05).  
- Marketing segment rule `clv_tier in [...]` (07).  
- Waitlist priority boost (15).  
- Intelligence “protect these VIPs” card (02).

---

### 7. Dependencies

- Clean payment/completion data.  
- Soft dependency on retention module for ROI story.  
- Not required for Arabic/UI work.

---

### 8. Phasing

| Phase | Scope |
|-------|--------|
| P0 | Denorm spend/visits + tier badge on customer profile |
| P1 | Score + sort in win-back lists |
| P2 | Segment rules + waitlist boost |
| P3 | Predictive model (optional) |

---

### 9. Open decisions

1. Percentile vs absolute tiers for small salons (unstable percentiles).  
2. Show exact score to Staff or only VIP badge.  
3. Include retail product spend in CLV?

---

### 10. Acceptance criteria

- After completed paid visit, customer stats update by next job run.  
- Platinum sort appears above Bronze in win-back default sort.  
- Changing weights recalculates on next run without editing history appointments.

---

## 19 — Real Shift & Attendance Management

**Category:** Staff  
**Status:** Suggested (Appendix C excludes full attendance)  
**Priority:** Low  
**As of:** 21 September 2026

---

### 1. Summary

`users.weekly_schedule` is a **static weekly template** for booking availability — not clock-in, shift swaps, absences, or overtime. Real attendance strengthens payroll (09) and utilization insights (02/08).

**GTM note:** Still fine to keep out of core marketing claims until built; this doc is planning only.

---

### 2. Current state

| Exists | Missing |
|--------|---------|
| `weekly_schedule` JSON | Shift instances per date |
| Booking conflict vs schedule | Clock-in/out |
| `per_month_salary` | Absence deductions |
| — | Leave requests / approvals |

---

### 3. Change level

| Layer | Intensity |
|-------|-----------|
| Database | High |
| Staff mobile/web clock UI | High |
| Manager approval flows | Medium |
| Availability integration | Medium–High |
| Payroll dependency | Optional |

---

### 4. Field & entity changes

#### 4.1 `shifts`

| Field | Notes |
|-------|--------|
| `saloon_id`, `branch_id`, `user_id` | |
| `starts_at`, `ends_at` | concrete datetime |
| `source` | `generated_from_weekly`, `manual` |
| `status` | `scheduled`, `completed`, `cancelled` |
| `break_minutes` | |

#### 4.2 `attendance_punches`

| Field | Notes |
|-------|--------|
| `user_id`, `branch_id` | |
| `type` | `in`, `out`, `break_start`, `break_end` |
| `punched_at` | |
| `method` | `app`, `kiosk`, `manager_override` |
| `geo_lat/lng` optional | policy |
| `created_by` | |

#### 4.3 `attendance_days` (summary)

| Field | Notes |
|-------|--------|
| `user_id`, `date` | |
| `scheduled_minutes`, `worked_minutes` | |
| `late_minutes`, `early_leave_minutes` | |
| `status` | `present`, `absent`, `holiday`, `leave` |
| `exception_reason` | |

#### 4.4 `leave_requests`

| Field | Notes |
|-------|--------|
| `type` | annual, sick, unpaid |
| `status` | pending/approved/rejected |
| `from_date`, `to_date` | |
| `approver_id` | |

#### 4.5 Link to availability

When leave approved or absence marked → block booking availability for that staff (override weekly_schedule).

---

### 5. Role impact

| Role | Define shift templates | Edit day shifts | Clock in/out | Approve leave | Correct punches | View timesheets |
|------|------------------------|-----------------|--------------|---------------|-----------------|-----------------|
| **Franchise Owner** | Yes | Yes | No need | Yes | Yes | All |
| **Franchise Manager** | Yes | Yes | Optional | Yes | Yes | All |
| **Branch Manager** | Branch | Branch | Optional | Branch | Branch | Branch |
| **Staff** | No | Request swap optional | **Yes** | Request | No | Own |
| **Platform Admin** | — | — | — | — | — | — |

Permissions: `attendance.view`, `attendance.manage`, `attendance.punch`, `leave.approve`.

---

### 6. Business logic

#### 6.1 Shift generation

Weekly cron expands `weekly_schedule` into `shifts` for next N days; managers edit exceptions.

#### 6.2 Punch rules

- Clock-in allowed from X minutes before shift.  
- Missing clock-out → manager alert; auto-close at shift end optional flag.  
- Late threshold → `late_minutes`.

#### 6.3 Absence

No punch + scheduled shift → `absent` after grace; optionally free that staff’s appointments (careful — may need reassign workflow).

#### 6.4 Leave

Approved leave creates availability blocks; rejects booking; notifies customers of affected appointments (ops policy).

#### 6.5 Utilization metrics

`worked_minutes / scheduled_minutes` feeds benchmarking (08) and Intelligence (02).

#### 6.6 Payroll bridge

Export worked minutes + absences to pay runs (09) — do not claim full payroll here.

---

### 7. Dependencies

- Soft: payroll (09), commissions still independent.  
- Hard for accuracy: staff actually use punch UI.  
- Geo-fencing may be sensitive in workplace law — optional.

---

### 8. Phasing

| Phase | Scope |
|-------|--------|
| P0 | Leave requests + availability block (no punches) |
| P1 | Shift calendar + manual mark present/absent |
| P2 | Clock-in/out + timesheets |
| P3 | Swap requests + kiosk mode |

---

### 9. Open decisions

1. Stay Appendix C excluded in sales until P1+? **Yes recommended.**  
2. Mandatory geo punch?  
3. Auto-cancel client appointments on sick leave?

---

### 10. Acceptance criteria

- Approved leave prevents new bookings for that staff.  
- Timesheet totals match punches within rounding rules.  
- Staff cannot edit historical punches.  
- Branch Manager cannot approve leave for other branches.

---

## 20 — Dynamic / Off-Peak Pricing

**Category:** Pricing / Demand  
**Status:** Suggested  
**Priority:** Low  
**Depends on:** AI insights day-of-week imbalance (02)  
**As of:** 21 September 2026

---

### 1. Summary

Incentivize bookings on low-utilization days/hours that Salon Intelligence surfaces, via **rules-based** price adjustments (not fully automatic ML pricing in v1). Extends tenant pricing (`salon_service_products.price`).

---

### 2. Current state

- Master `default_price` + per-salon/branch `salon_service_products.price`.  
- Appointment-level `discount` manual.  
- No time-based or demand-based price rules.  
- Public booking shows static prices.

---

### 3. Change level

| Layer | Intensity |
|-------|-----------|
| Database price rules | Medium |
| Price resolution at booking | High |
| Public booking UI | Medium |
| Insights CTA integration | Low–Medium |
| Permissions | Medium |

---

### 4. Field & entity changes

#### 4.1 `pricing_rules`

| Field | Notes |
|-------|--------|
| `saloon_id`, `branch_id` nullable | |
| `name` | “Sunday afternoon −15%” |
| `is_active` | |
| `priority` | |
| `adjustment_type` | `percent_off`, `percent_on`, `fixed_price`, `fixed_off` |
| `adjustment_value` | |
| `service_ids` / `category_ids` JSON | empty = all |
| `staff_ids` JSON | optional |
| `days_of_week` | [0–6] |
| `time_start`, `time_end` | local |
| `date_from`, `date_to` | optional season |
| `min_lead_hours` | book ≥ X hours ahead |
| `channel` | `all`, `self_booking`, `internal` |
| `stackable` | with manual discount? default false |

#### 4.2 Appointment / line snapshots

| Field | Notes |
|-------|--------|
| `list_price` | before rule |
| `applied_pricing_rule_id` | |
| `rule_adjustment_amount` | |
| Keep `price` as final | |

Critical: **freeze price at booking time** so later rule edits do not change historical tickets.

#### 4.3 Insight link

Intelligence action `promo_offpeak` pre-fills a rule draft from underused weekday (02).

---

### 5. Role impact

| Role | Create rules | Activate | See effective price in calendar | Override price on ticket | Configure stacking |
|------|--------------|----------|---------------------------------|--------------------------|--------------------|
| **Franchise Owner** | Yes | Yes | Yes | Yes | Yes |
| **Franchise Manager** | Yes | Yes | Yes | Yes | Yes |
| **Branch Manager** | Branch rules if allowed | Branch | Yes | Yes | No |
| **Staff** | No | No | Sees final price | Limited discount if existing rights | No |
| **Customer** | — | — | Sees promotional price on booking | — | — |

Permissions: `pricing_rules.view`, `pricing_rules.manage`.

---

### 6. Business logic

#### 6.1 Price resolution order

1. Base: `salon_service_products.price` (branch → salon → master default).  
2. Find active matching rules; pick highest `priority`.  
3. Apply adjustment → `final_price`.  
4. Apply manual appointment discount only if `stackable` or rule absent.  
5. Persist snapshot on lines.

#### 6.2 Matching dimensions

Must match: branch, service/category, day, time window, channel, date range, staff if set.

#### 6.3 Guardrails

- Floor price: never below `min_price` (optional per service).  
- Ceiling for percent_on (surge) — optional; surge may anger clients — default **off-peak discounts only** for v1.  
- Clear UI badge: “Off-peak price”.  
- Commission basis: decide gross list vs final (align with doc 04 `percent_of_net`).

#### 6.4 Public booking

Show strikethrough list vs promo where legal/marketing prefers transparency.

#### 6.5 Insight-driven suggestion

When day-of-week revenue &lt; threshold, suggest: “Create −10% rule for Tuesday 14:00–17:00, Hair category.”

Human must confirm — no auto-publish in v1.

---

### 7. Dependencies

- Soft: Intelligence (02) for suggestions.  
- Hard: Consistent timezone handling.  
- Commission engine should know final vs list (04).  
- Packages/gift cards (06) redemptions usually ignore promo (define).

---

### 8. Phasing

| Phase | Scope |
|-------|--------|
| P0 | Daypart percent_off rules + booking snapshot |
| P1 | Public booking display + non-stacking with manual discounts |
| P2 | Insight “create rule” CTA |
| P3 | Surge pricing / demand ML (optional, careful) |

---

### 9. Open decisions

1. Discounts only vs allow surge? **Recommend discounts only.**  
2. Commission on promotional price or list?  
3. Branch Manager autonomy on deep discounts (cap %)?  

---

### 10. Acceptance criteria

- Booking Tuesday 15:00 applies Tuesday rule; Wednesday does not.  
- Editing rule does not change past appointment prices.  
- Public booking and POS show the same resolved price for same slot.  
- Rule floor prevents zero/negative prices.

---

---

*End of document — Saloenza Feature Gap Detailed Planning (All Points). As of 21 September 2026.*
