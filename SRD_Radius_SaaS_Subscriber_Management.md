# System Requirements Document (SRD) — Development-Ready
**Project Name:** RADIUS SaaS — Multi-Tenant ISP Subscriber Management & Billing Platform

**Document Version:** 2.0 — Development-Ready
**Date:** 2026-08-26
**Status:** Approved for Build (P0)
**Author:** Platform Engineering (GitHub Copilot assisted)

---

## 0. Purpose & Scope

A **multi-tenant SaaS** for ISPs / WISPs / LCOs to manage subscribers, plans, billing, and network sessions through a web UI and REST API.

**Architectural boundary (fixed):** This system is the **control plane / management SaaS** only. Several backend systems already exist as **external servers** and MUST NOT be rebuilt here — this platform integrates with each via its API/adapter. The full list and interfaces are in **§2.3 External Systems (Do Not Build)**. The RADIUS server (already built, API at `http://127.0.0.1:8001/api`, docs `http://127.0.0.1:8001/api/manual`) is the primary dependency; the others are integration points consumed via adapters.

| In scope (build here) | Out of scope (external systems — integrate only) |
|---|---|
| Tenant mgmt, RBAC, white-label | RADIUS/AAA protocol handling |
| Subscriber lifecycle UI + logic | NAS / OLT communication |
| Plan catalog → RADIUS profiles | Real-time session termination / FUP enforcement |
| Billing, GST, wallet, payments | Raw IPDR ingestion |
| Dashboards, KYC docs, notifications UI | Call/SMS, WhatsApp, TR-069, OLT/ONT, NMS backends |
| Integration layer (all external API clients) | All external servers listed in §2.3 |

---

## 1. Decisions Locked (read first)

| Decision | Choice |
|---|---|
| Language / UI | PHP backend (**Laravel**), HTML/JS/CSS frontend (server-rendered) |
| Database | PostgreSQL 15+ with Row-Level Security (RLS) |
| Cache / Queue | Redis **(future)**; start with in-memory + PostgreSQL job table |
| Architecture | Clean / Hexagonal (Ports & Adapters) |
| Multi-tenancy | PostgreSQL RLS keyed on `tenant_id` + app-layer guardrail |
| UI themes | **Clean Enterprise** (light, default) + **Dark Ops** (dark), on-the-fly toggle |
| External dependencies | 7 external servers (RADIUS, IPDR, Call/SMS, WhatsApp, TR-069/ACS, OLT/ONT, NMS) — integrate only, do NOT build (§2.3) |
| Nav structure | 17-menu admin map (§5.0), copied from existing console |

---

## 2. Architecture & Topology

### 2.1 Topology
```
   EXTERNAL SYSTEMS (all already built — DO NOT BUILD here, §2.3)
   ------------------------------------------------------------------
   RADIUS Server (AAA/Acct/CoA/PoD)   IPDR Server   Call/SMS Server   WhatsApp Server
   TR-069/ACS Server   OLT/ONT Mgmt System   Network Mgmt & Map System
                    ^  REST/JSON (control + query)  —— all via adapters
                    |
   +--------------------------------------------------------------+
   |  RADIUS SaaS PLATFORM (this system — control plane)         |
   |  Web UI (HTML/JS/CSS) <-> PHP Backend (REST API) <-> Workers|
   |        |                    |                    |          |
   |  PostgreSQL (RLS)    Redis (cache/queue, FUTURE)  Object Store|
   +--------------------------------------------------------------+
        ^ (ISP Admin)   ^ (LCO/Franchise Wallet)   ^ (Subscriber Self-Service)
   (NAS/OLT/AP talk to the EXTERNAL RADIUS/OLT servers, not to this platform)
```

### 2.2 Module Map
| Module | Responsibility | External API? |
|---|---|---|
| Tenant & RBAC | Onboarding, white-label, roles | No |
| Subscriber Mgmt | CRUD, lifecycle, KYC links | RADIUS (Create/Activate/Suspend) |
| Plan Catalog | Plans → RADIUS profiles | RADIUS (push profile) |
| Session Ops | View sessions, CoA, PoD | RADIUS (query/CoA/PoD) |
| NAS Registry | Manage NAS per tenant | RADIUS (register NAS) |
| Billing & GST | Invoices, wallet, payments | No |
| Compliance | KYC, audit, IPDR | RADIUS (auth-logs); IPDR Server (§2.3 #7) |
| Notifications | SMS/WhatsApp/Email | Call/SMS + WhatsApp + Email servers |

### 2.3 External Systems — DO NOT BUILD
These backend systems are **already built and operated externally**. This project integrates with them via adapters/API clients only. They are explicitly **out of scope** for development.

| # | External System | Role | Integration (this platform) | Consumed via |
|---|---|---|---|---|
| 1 | **RADIUS Server** | AAA, accounting, CoA/PoD, sessions, plans, NAS, MAC, FUP (single-tenant) | Provisioning, session control, accounting, auth-logs | `RadiusClient` port → `HttpRadiusAdapter` (§4, §7.2) |
| 2 | **Call / SMS Server** | OTP, transactional & promotional SMS, voice calls | OTP verification, alerts, notifications | `NotificationSender` port → `SmsAdapter` / `VoiceAdapter` |
| 3 | **WhatsApp Server** | WhatsApp Business messaging (invoice links, receipts) | Rich notifications to subscribers | `NotificationSender` port → `WhatsAppAdapter` |
| 4 | **TR-069 / ACS Server** | Auto-Configuration of ONU/ONT (optical power dBm, SSID, remote reboot) | Device mgmt views, remote actions | `AcsClient` port → `HttpAcsAdapter` (P3) |
| 5 | **OLT / ONT Management System** | OLT/ONT provisioning & monitoring | Device inventory, status, optical metrics | `OltClient` port → `HttpOltAdapter` (P3) |
| 6 | **Network Management & Map System** | Topology, monitoring, network diagram, outage | Network Mgmt menu (§5.0 #8) | `NmsClient` port → `HttpNmsAdapter` (P3) |
| 7 | **IPDR Server** | Internet/Call Detail Record retention + retrieval (2-yr searchable export), compliance feed | IPDR viewer, export, audit/reconciliation | `IpdrClient` port → `HttpIpdrAdapter` (§4.3, §7.2) |

**Rules:**
- Each external system is reached **only** through a **port (interface)** + **adapter** in the Clean/Hexagonal layout (§7.2) — never directly from domain/use-case code.
- Connection details (base URL, credentials) are **environment config + secrets vault**, never hardcoded; assume different endpoints per deployment/tenant realm.
- If an external system is unavailable, the relevant feature degrades gracefully (queue/retry per §4.1) — the platform never tries to reimplement it.
- Adding a new external system = add a port + adapter; **no change to domain or use-case code** (architecture guarantee).

---

## 3. Multi-Tenancy & Theming

### 3.1 Isolation
- **RLS** on `tenant_id`; each DB session runs `SET app.current_tenant = '<id>'` before queries.
- **Guardrail:** all persistence goes through repository *adapters* that auto-inject `tenant_id`; no raw SQL bypasses the port.
- **Routing:** host-header resolution (`tenant.platform.com` or CNAME); unknown host → default/404.
- **White-label:** per-tenant logo, colors, invoice header, SMTP/SMS sender, portal copy.
- **Quotas:** per-tenant subscriber/NAS/API-rate/storage limits (app layer + billing tier).

### 3.2 UI Themes (mandatory on-the-fly switch)
| Theme | Mode | Palette | Use |
|---|---|---|---|
| Clean Enterprise | Light | Blue `#2563EB` on `#F8FAFC`/`#FFFFFF` | Default — admin & LCO |
| Dark Ops | Dark | Cyan `#22D3EE` on `#0B1120`/`#111827` | NOC / session screens |

Requirements:
- Toggle in top bar, **instant, no reload** (CSS custom properties / `data-theme`).
- Persisted **per user** (profile + `localStorage`); tenant default overridable per user.
- WCAG 2.1 AA contrast; keyboard-accessible; screen-reader announced.
- Implemented as design tokens; composes with per-tenant white-label tokens.

---

## 4. RADIUS Server API Integration (Contract)

> **✅ VALIDATED 2026-08-26** against the live `http://127.0.0.1:8001/api/manual`. Key finding: the RADIUS core is **single-tenant** (no realm/tenant concept) and the RADIUS API surface exposes **no IPDR endpoint** — IPDR is a **separate external server** (§2.3 #7, §4.3), not a RADIUS sub-capability. See §4.1.1 for the isolation implication.

### 4.1 Connection & Resilience
- **Base URL:** `RADIUS_API_BASE` (default `http://127.0.0.1:8001/api`).
- **Auth:** **Bearer JWT** obtained via `POST /api/auth/login` (username/password from secrets/config), valid 12h, cached by the adapter and auto-refreshed on `401`. Replaces the earlier "static API key" assumption — the adapter owns the login flow.
- **Rate limit:** server enforces 1000 req/hr/IP; the adapter should batch and back off.
- **Resilience:** circuit breaker + retry-with-backoff. On RADIUS API outage, provisioning actions are **queued** (PostgreSQL job/Dead-Letter table now; Redis later) and reconciled; UI shows "pending sync".

### 4.1.1 Single-Tenant RADIUS — Critical Isolation Implication
The RADIUS core is **single-tenant**: it has **no realm/tenant concept** and performs no tenant isolation. Therefore:
- **All tenant isolation is owned by this platform** (PostgreSQL RLS + app guardrail, §3.1). The RADIUS server will act on any username it is given.
- **RADIUS `username` MUST be tenant-namespaced** to prevent collisions and accidental cross-tenant session control. The `RadiusClient` adapter derives `radius_username = tenant_slug . '_' . local_username` (or reads a stored mapping) before calling the API; the local `subscribers.username` stays tenant-local.
- The earlier "tenant → RADIUS realm mapping" question is **moot** — there is no realm; isolation is purely our responsibility. (See §11.)

### 4.2 Endpoints (VALIDATED against live `/api/manual`, 2026-08-26)
All routes require a Bearer JWT except `/api/auth/login` and `/api/health`. Base: `RADIUS_API_BASE`. The RADIUS `username` passed to these routes MUST be tenant-namespaced per §4.1.1.

| Method | Path | Purpose |
|---|---|---|
| POST | `/api/auth/login` | Issue JWT (body: username, password) |
| GET | `/api/health` | Liveness + DB health |
| GET | `/api/health/stats` | Aggregate counts |
| GET | `/api/users` | List users (subscribers) |
| GET | `/api/users/:id` | User by id |
| GET | `/api/users/username/:username` | User by username |
| POST | `/api/users` | Create user (req: username, password, plan_id) |
| PUT | `/api/users/:id` | Update allowed fields (email, static_ip, status, plan_id, …) |
| DELETE | `/api/users/:id` | Delete user |
| GET | `/api/users/:id/macs` | List MAC bindings |
| POST | `/api/users/:id/macs` | Add MAC binding |
| DELETE | `/api/users/:id/macs/:mac` | Remove MAC binding |
| GET | `/api/users/:id/data-limit` | Data-limit check |
| GET | `/api/users/:id/fup` | FUP status |
| GET | `/api/plans` | List plans |
| GET | `/api/plans/:id` | Plan by id |
| POST | `/api/plans` | Create plan (req: name) |
| PUT | `/api/plans/:id` | Update plan |
| DELETE | `/api/plans/:id` | Delete plan |
| GET | `/api/nas` | List NAS |
| GET | `/api/nas/:id` | NAS by id |
| POST | `/api/nas` | Create NAS (req: nas_ip, shared_secret) |
| PUT | `/api/nas/:id` | Update NAS |
| DELETE | `/api/nas/:id` | Delete NAS |
| GET | `/api/sessions` | All active sessions |
| GET | `/api/sessions/:sessionId` | Session by id |
| GET | `/api/sessions/user/:username` | Sessions for a user |
| GET | `/api/sessions/:sessionId/history` | Session history |
| POST | `/api/sessions/:sessionId/disconnect` | PoD disconnect (Disconnect-Request) |
| POST | `/api/sessions/:sessionId/bandwidth` | CoA bandwidth change (MikroTik-Rate-Limit) |
| POST | `/api/sessions/disconnect/user/:username` | Disconnect all user sessions |
| POST | `/api/coa/disconnect` | CoA disconnect (by session_id/ip) |
| POST | `/api/coa/bandwidth` | CoA bandwidth |
| POST | `/api/coa/disconnect-user` | Disconnect all user sessions |
| GET | `/api/accounting` | List records (?limit,&offset) |
| GET | `/api/accounting/session/:sessionId` | Record by session |
| GET | `/api/accounting/user/:username` | Records by user |
| GET | `/api/accounting/range` | Records in window (?from,&to) |
| GET | `/api/accounting/data-usage/:userId` | Usage by user id |
| GET | `/api/accounting/data-usage/user/:username` | Usage by username |
| GET | `/api/auth-logs` | Recent auth logs |
| GET | `/api/auth-logs/user/:username` | Logs for a user |

> **Notes:** RADIUS stores passwords as bcrypt + an AES-reversible `chap_password`; the platform sends the plaintext `password` at create time and need not retain a separate reversible copy for RADIUS. MAC-lock behavior and the 1000 req/hr/IP limit are server-enforced. **No IPDR endpoint exists** in the RADIUS API — IPDR is owned by the separate IPDR Server (§2.3 #7, §4.3).

### 4.3 IPDR Server API Integration (Contract)
The **IPDR Server** is a distinct external system (#7 in §2.3), reached only through the `IpdrClient` **port** implemented by `HttpIpdrAdapter` (§7.2). It is **not part of the RADIUS API** — the RADIUS server exposes no IPDR endpoint.

- **Base URL:** `IPDR_API_BASE` (env; default TBD per deployment).
- **Auth:** **Bearer JWT** (assumed same login flow as RADIUS — `POST /auth/login` → 12h token cached + auto-refresh on 401). Confirm exact scheme against the IPDR Server manual when available.
- **Purpose:** Internet/Call Detail Record retention + retrieval — 2-year searchable export and compliance feed (§5.5, §6).
- **Resilience:** same pattern as §4.1 — circuit breaker + retry-with-backoff; on outage the IPDR viewer/export degrades gracefully (show "IPDR unavailable") and reconciliation (`ReconcileIpdr` use case, §3.2) retries.
- **Endpoints (provisional — confirm vs IPDR Server manual):**
  | Method | Path | Purpose |
  |---|---|---|
  | GET | `/ipdr/query` | Search records by subscriber/session/time window |
  | GET | `/ipdr/export` | Export (CSV/JSON) for compliance window |
  | GET | `/ipdr/:id` | Single record by id |
- **Status:** P2 (§10). The viewer is **unblocked at the architecture level** — it depends on the `IpdrClient` adapter + IPDR Server endpoint, not on RADIUS.

### 4.4 Consistency
- PostgreSQL = business truth (plan, wallet). RADIUS server = network truth (sessions/auth).
- **Reconciliation worker** diffs local vs RADIUS and flags drift (e.g., locally active but suspended at NAS).

---

## 5. Functional Requirements

### 5.0 Admin Portal Navigation (authoritative menu map)
Parsed from the existing console; routes under `tenant.platform.com/<route>`.

| # | Menu | Sub-Items |
|---|---|---|
| 1 | Dashboard | — |
| 2 | Administration | Branch · Call Center Mgmt · Location Mgmt · Manage Shifts · Custom Templates · Company Profile · Roles · Telephone Plans · Admin Users · Settings · Department · User Groups · Manage Financial Yr · Templates · Track Emp in Mobile · Content Management · Promotional Banners |
| 3 | Franchise Mgmt | Franchise List · Add Franchise · Deposit Reports · Payment Reports · Ledger Report · Franchise Wallet · Copy Sharing · Franchise Settlement · Commission Report · Sharing Report · Revenue Report · SMS Deposits · Share & Price Mgmt · Renewal Report |
| 4 | Radius Cpanel | Package · NAS · IpPool · Towers · AAA Cluster Nodes · Offers · QoS Management · Dynamic Rates · Live Logs |
| 5 | User | User List · Add User · Search User · Online User · Pickup Request · Bulk Operations · Consolidate Online · Renew Scheduled · Voucher Management · Feedback · Session History · Disconnected · User Adon Services · Requests |
| 6 | Complaints | Add · All · My Plate · Open · In Progress · On Hold · Resolved · Closed · Complaint Category · Rating By Customers · Manage SLA |
| 7 | Leads | Add · All · My Leads · Open · In Progress · Leads Documents · Callback · Confirm Fsb Pending · CAF Pending · NC Pending · Active & Closed · Not interested · Other Closed |
| 8 | Network Mgmt | Nas Graph · Device Management · List Moniters · Moniters in Map · Network Diagram · Mass Outage |
| 9 | Reports | Proforma Invoices · Tax Invoices · Payment History · Traffic Report · Credit & Debit Notes · Online Payments · User Wallet History · User Specials Report · Smart Paylink Report · OTT Report · Tax Reports · Cancelled Invoices |
| 10 | Logs | Audit Logs · Login History · Login Fail Attempts · SMS Logs · Email Logs · Call Logs · WhatsApp Logs · Aadhaar Logs · User Syslogs |
| 11 | Promotions | Send Sms · Send Email · Announcements · Coupon Mgmt |
| 12 | HR & Payroll | Manage Employees · Manage Salaries · Add Employee · Attendance Report · In & Out Report · Configuration · Roaster Management |
| 13 | Inventory Mgmt | Vendors & Suppliers · Product Categories · Products · Add Stock · Manage Stock · Inventory Locations · Stock Upload History · Stock Items · Bulk Transfer · Transfer History |
| 14 | Business Analytics | Report Builder · Report Gallery · Custom Dashboard · Dashboard Widgets |
| 15 | Tools | Bulk Uploads · Server Information · Custom Fields · License Information · Recycle Bin · User Migration · FUP Data Update · Bulk Operations · Dynamic Update |
| 16 | Finance Management | Expense Management · Income Management · Category · Account List · Transactions · Transfer |
| 17 | App Support | — |

**Scope tags:** Core build (most) · Reads RADIUS (Radius Cpanel NAS/IPPool/AAA, User Online/Session History/Disconnected, Live Logs) · P3+ (HR & Payroll, Inventory, Analytics builder). **RBAC:** menu visibility follows Super Admin → ISP Admin → LCO → Technician → Subscriber.

> **P0 scope decision (2026-08-26):** Menu items whose backend entities are not in §8 (`branches`, `departments`, `roles`, `complaints`, `leads`, `telephone_plans`, HR/Inventory/Analytics) are **deferred to P1+** and MUST render a "Coming Soon" placeholder in P0 — they are not stubbed in the P0 data model. The P0 buildable surface = §8 entities only (tenant, users, subscribers, plans, wallets, nas, kyc, sessions_cache, audit_log, jobs).

> **⚠️ Known inconsistencies in source menu (fix at build):** label "User Groups" → route `/administration/dunning`; "Share & Price Mgmt" under Franchise but route `/reseller/...`; "Manage Financial Yr" → `/fyi/...`. Keep labels for parity; correct routes.

### 5.1 Subscriber Lifecycle
- Onboarding wizard: create → KYC → plan → push RADIUS (`POST /api/users` with tenant-namespaced `username`, `plan_id`, `status:active`) (§4.2). Activation is implicit on create with an active status + valid plan.
- Provisioning types (delegated): PPPoE, Static IP, DHCP, MAB, Hotspot/Captive Portal.
- States: `PROSPECT → KYC_PENDING → READY → ACTIVE → SUSPENDED → EXPIRED → DELETED`.
- Bulk CSV import/export with validation + staged activation.
- Self-service portal: usage, pay, tickets, expiry.

### 5.2 Plan & Product Catalog
- Plan = price, billing cycle, speed profile, FUP policy; maps to a RADIUS plan (`POST /api/plans`, §4.2) carrying `bandwidth_*_mbps`, `data_limit_gb`, `duration_days`, FUP thresholds.
- Publish → ensure RADIUS plan exists (`POST /api/plans`). Plan change → CoA bandwidth to active sessions (`POST /api/sessions/{id}/bandwidth`).

### 5.3 Session Ops & Network Control
- Live session grid (`GET /api/sessions`): user, NAS, IP, uptime, bytes.
- PoD kick (`POST /api/sessions/{sessionId}/disconnect`); CoA throttle (`POST /api/sessions/{sessionId}/bandwidth`, FUP/manual).
- NAS registry per tenant (`POST /api/nas` with `nas_ip`, `shared_secret`, `type`); validated before auth.

### 5.4 LCO / Franchise Hierarchy
- Roles: Super Admin → ISP Admin → LCO Manager → Technician → Subscriber.
- Prepaid wallet: positive balance to activate/renew; top-up via gateway; auto-ledger; ISP cost deducted on activation, LCO margin locked.
- Credit/overdraft: admin-configurable per LCO.

### 5.5 Indian Compliance
- **IPDR viewer / export:** Owned by the **IPDR Server** (external system #7, §2.3), integrated via the `IpdrClient` port → `HttpIpdrAdapter` (§4.3, §7.2). The viewer/export is **unblocked at the architecture level** — it depends on the IPDR Server endpoint + `IpdrClient` adapter, **not** on RADIUS (the RADIUS API exposes no IPDR endpoint). Status: **P2** (§10). Design the `Compliance` module to degrade gracefully (show "IPDR unavailable") until the `IpdrClient` adapter + IPDR Server endpoint are wired.
- **KYC:** Aadhaar e-KYC / DigiLocker / OCR POI-POA; mandatory mobile OTP; docs encrypted at rest.
- **GST:** CGST/SGST vs IGST by place of supply; SAC `998422`; QR invoices; e-Invoicing ready.

### 5.6 Payments, Notifications & Integrations
- Gateways: Razorpay, Paytm, PayU, PhonePe, BBPS; auto-reconciliation.
- Notifications: WhatsApp Business API, DLT SMS, email.
- TR-069/ACS (ONT power dBm, SSID, reboot): optional, P3+.

---

## 6. Non-Functional Requirements
| Metric | Requirement | Owner |
|---|---|---|
| Mgmt-plane availability | 99.9% | This platform |
| RADIUS control latency | ≤ 200 ms p95 (command accepted) | Integration |
| RADIUS data-plane SLOs | 99.99% auth, ≤50 ms, ≤20 ms acct | External server |
| Scalability | ≥10,000 tenant-subscriptions/node; horizontal scale | This platform |
| IPDR retention | 2-yr searchable export | External + platform export |
| Data protection | AES-256 column encryption; TLS 1.3; secrets vault | Both |
| Auditability | Immutable audit log (operator + timestamp) | This platform |

---

## 7. Technology Stack & Project Structure

### 7.1 Stack
- **Backend:** **PHP (Laravel)** — REST/JSON, Blade server-rendered views (auto-escaping by default, §9.2), queued workers via Laravel Queue (DB driver now, Redis later).
- **Frontend:** HTML/JS/CSS server-rendered; theming via CSS variables (§3.2).
- **DB:** PostgreSQL 15+ with RLS.
- **Cache/Queue:** Redis (future); PostgreSQL job table now.
- **Object storage:** S3-compatible / local, encrypted at rest.
- **Infra:** Linux + Nginx/PHP-FPM; Docker optional; CI/CD.

### 7.2 Clean / Hexagonal Layers
1. **Domain:** `Subscriber`, `Plan`, `Wallet`, `Tenant`, `Invoice`, `Nas` — pure, no framework/DB imports.
2. **Application (use cases):** `ProvisionSubscriber`, `RenewPlan`, `TerminateSession`, `ReconcileIpdr` — depend on ports only.
3. **Ports (interfaces):** `SubscriberRepository`, `InvoiceRepository`, `WalletRepository`, `RadiusClient`, `IpdrClient` (P2), `PaymentGateway`, `NotificationSender` (SMS/Voice/WhatsApp), `AcsClient` (TR-069), `OltClient`, `NmsClient`, `Cache`.
4. **Adapters:** `Postgres*Repository`, `HttpRadiusAdapter`, `HttpIpdrAdapter` (P2), `RazorpayAdapter`, `SmsAdapter`, `VoiceAdapter`, `WhatsAppAdapter`, `HttpAcsAdapter` (P3), `HttpOltAdapter` (P3), `HttpNmsAdapter` (P3), `RedisCacheAdapter` (future).
5. **Delivery:** server-rendered views; front controller + middleware (tenant resolve, authn/authz, set RLS context).
- **Composition root** wires adapters → use cases. No use case may `new` an adapter.

### 7.3 Recommended Folder Structure (Laravel-style)
```
app/
  Domain/                 # entities, value objects, invariants
  Application/
    UseCases/             # ProvisionSubscriber, RenewPlan, ...
    DTOs/
  Ports/                  # interfaces (Repository, RadiusClient, ...)
  Adapters/
    Persistence/          # Postgres*Repository (RLS + tenant_id)
    Radius/               # HttpRadiusAdapter
    Payments/             # RazorpayAdapter
    Notifications/        # SmsAdapter, VoiceAdapter, WhatsAppAdapter
    Devices/              # HttpAcsAdapter (TR-069), HttpOltAdapter (P3)
    Network/              # HttpNmsAdapter (P3)
  Delivery/
    Http/Controllers/     # thin controllers -> use cases
    Middleware/           # TenantResolve, Auth, RlsContext
    Views/                # view builders/render layer (templates in resources/views)
  Infrastructure/         # DB migrations, queue, bootstrap (composition root)
routes/
  admin.php  lco.php  subscriber.php
resources/
  css/themes/             # tokens-light.css, tokens-dark.css
  views/                  # server-rendered templates (single source of truth for UI)
database/
  migrations/             # schemas with tenant_id + RLS policies
config/
  radius.php  services.php
```

---

## 8. Data Model (core entities, P0)
All tables include `tenant_id` (RLS) + `created_at`/`updated_at`.

| Entity | Key fields |
|---|---|
| `tenants` | id, name, domain, logo_url, theme_default, brand_* , status |
| `users` (staff) | id, tenant_id, role, email, password_hash, theme_pref |
| `subscribers` | id, tenant_id, username, password_enc (AES-256, **recoverable** — sent to RADIUS), mac, static_ip, plan_id, status, kyc_id, expiry |
| `plans` | id, tenant_id, name, price, cycle, speed_profile, fup_policy, radius_profile |
| `wallets` | id, tenant_id, owner_type (LCO/USER), balance, credit_limit, currency |
| `wallet_ledger` | id, wallet_id, txn_type, amount, ref |
| `invoices` | id, tenant_id, subscriber_id, gst_type, sac, amount, qr, status |
| `nas` | id, tenant_id, ip, secret (enc), vendor, status |
| `kyc` | id, tenant_id, subscriber_id, poi_type, poa_type, doc_refs, status, verified_at |
| `sessions_cache` | id, tenant_id, subscriber_id, nas_id, ip, bytes_in/out, started_at |
| `audit_log` | id, tenant_id, actor_id, action, object, ts |
| `jobs` / `jobs_failed` | id, tenant_id, payload, status, attempts (queue/DL) |

> **Note:** `tenants` is the tenancy root and does **not** carry a `tenant_id` (it *is* the tenant). All other tables above include `tenant_id`. `subscribers.password_enc` is **AES-256 encrypted (recoverable)** because the plaintext is required to provision RADIUS — this is distinct from `users.password_hash` (bcrypt/Argon2, **irrecoverable**, per §9.4). `wallets.credit_limit` backs the per-LCO overdraft in §5.4.

---

## 9. Security & Compliance

Security is **mandatory and built-in**, not optional. The platform must satisfy OWASP Top-10 (2021) for a multi-tenant PHP web app. Each control below is a P0 requirement unless noted.

### 9.1 Tenant Isolation (defense in depth)
- PostgreSQL **RLS** on `tenant_id` **and** app-layer ownership checks; mandatory tenant-scoped repositories (no raw SQL bypass).
- **Host-header tenant resolution** must be validated against the allowed domain list to prevent tenant-spoofing via forged `Host` headers.
- Cross-tenant object access rejected at authorization middleware even when RLS is correct.

### 9.2 Input Validation & Injection Defense
- **XSS (Cross-Site Scripting):** All user-supplied data is **contextually output-encoded** at render time (HTML body, HTML attribute, JS string, URL). Server-rendered templates MUST auto-escape by default (e.g., Blade `{{ }}` / Twig `{{ }}`) and only allow `raw` output for vetted, internal content. Client-side JS must use `textContent`/`setAttribute` not `innerHTML` for dynamic values. A strict **Content-Security-Policy (CSP)** header is set (no `unsafe-inline` where avoidable; nonces for inline scripts).
- **SQL Injection:** No string-concatenated SQL anywhere. Use **prepared statements / parameterized queries** exclusively via repository adapters. RLS context set through bound parameters.
- **Command Injection:** Never pass user input to `exec`/`shell_exec`/`proc_open`; if unavoidable, strict allow-list + escaping.
- **LDAP / XPath injection:** Parameterize or validate if used.
- **XXE:** Disable external entity resolution in any XML parser (SOAP/payment callbacks).
- **Deserialization:** Never `unserialize` untrusted data; use JSON with strict schemas.

### 9.3 CSRF & Request Forgery
- **CSRF tokens** on every state-changing request (forms + AJAX). Token bound to session + (optionally) tenant. Unsafe methods (`POST/PUT/DELETE`) rejected without valid token.
- **SameSite=Lax/Strict** cookies; `Origin`/`Referer` checked for cross-origin writes.

### 9.4 Authentication & Session Management
- **Passwords:** bcrypt/Argon2 hashing; never store plaintext; enforce strength + breached-password check.
- **MFA** for staff (TOTP/WebAuthn), mandatory for Super Admin / ISP Admin.
- **Sessions:** random 32+ byte IDs; `HttpOnly`, `Secure`, `SameSite` cookies; idle + absolute timeout; session regen on privilege change (login, role switch).
- **Account lockout / backoff** after N failed attempts; CAPTCHA on repeated failures.
- **JWT/API tokens** (RADIUS client, payment webhooks) signed (HS256/RS256), short TTL, revocable; secrets in vault.
- **Logout** invalidates server-side session + clears tokens.

### 9.5 Authorization (Access Control)
- **RBAC** enforced server-side on every endpoint (not just hidden in UI): Super Admin → ISP Admin → LCO → Technician → Subscriber.
- **Object-level checks:** a user may only act on objects within their tenant/branch (fail-closed default).
- **Privilege escalation** prevented: role changes require elevated authority + audit.

### 9.6 Transport, Secrets & Crypto
- **TLS 1.3** (1.2 min) on all endpoints; HSTS enabled.
- **AES-256** for credentials at rest (subscriber PPPoE passwords, NAS secrets); key in KMS/vault, rotated.
- **Secrets** (RADIUS tokens, gateway keys, DB creds) in vault; never in code, logs, or client bundles.
- **Secure randomness** (`random_bytes`/CSPRNG) for tokens/IDs — never `mt_rand`/`time()`.

### 9.7 Rate Limiting, WAF & Abuse
- **Rate limiting** per IP + per user on auth, OTP, payments, and API (tenant quota aware).
- **WAF** (or framework firewall) on public portals; block common attack signatures.
- **Brute-force / credential-stuffing** protection (lockout + CAPTCHA + IP reputation).
- **File uploads** (KYC docs, banners): type/size allow-list, virus scan, stored outside webroot with random names, served via signed URLs.

### 9.8 Logging, Monitoring & Audit
- **Immutable audit log** of all provisioning/control actions (actor, action, object, timestamp, tenant).
- **No secrets in logs**; mask PII/credentials. Structured logs with tenant context.
- **Security monitoring:** alert on repeated auth failures, RADIUS API errors, cross-tenant-access attempts, privilege changes.
- **Login history / fail-attempt logs** exposed in Logs menu (§5.0).

### 9.9 Dependency & Supply-Chain
- **Dependency scanning** (Composer audit / SCA) in CI; pin versions; update promptly.
- No abandoned/unmaintained packages; review new adapters (payment, SMS) before merge.

### 9.10 Data Protection & Privacy (India)
- **GDPR / DPDP-India:** data minimization, explicit consent capture (KYC), right-to-erasure workflow.
- **DLP** on KYC document access; encrypted-at-rest object storage.
- **PII masking** in exports/logs; retention policies per data class.

### 9.11 Security Headers (mandatory on all responses)
`Content-Security-Policy`, `X-Content-Type-Options: nosniff`, `X-Frame-Options: DENY` (or CSP `frame-ancestors`), `Referrer-Policy`, `Permissions-Policy`, `Strict-Transport-Security`.


---

## 10. Roadmap
| Phase | Deliverable |
|---|---|
| **P0** | Tenant + RBAC + RLS; **security baseline (§9): XSS auto-escape + CSP, CSRF tokens, parameterized SQL, auth/session/MFA, security headers, audit log**; themes + on-the-fly toggle; folder skeleton + `RadiusClient` port/`HttpRadiusAdapter`; Subscriber CRUD + RADIUS push/activate; Plan catalog; Dashboard; data model + migrations |
| **P1** | Session ops (view/PoD/CoA); NAS registry; wallet + payments; notifications |
| **P2** | KYC; GST billing; IPDR viewer/export; self-service portal |
| **P3** | White-label polish; TR-069/ACS; analytics; HR/Inventory; multi-region; Redis adoption |

---

## 11. Development Readiness — Open Items & Risks
**Must resolve before/while building P0:**
1. **DONE (2026-08-26):** RADIUS API validated against live `/api/manual`. §4.2 now reflects the real contract. Critical findings: (a) RADIUS core is **single-tenant** → tenant isolation owned by platform, usernames must be namespaced (§4.1.1); (b) RADIUS exposes **no IPDR endpoint** — but IPDR is now a **separate external server (#7, §2.3)** integrated via `IpdrClient` (§4.3); the viewer is unblocked at architecture level and tracked as P2 (§5.5, §10).
2. **Framework choice:** Laravel vs Symfony — confirm (affects folder layout in §7.3 and templating/escape defaults in §9.2).
3. **RESOLVED (2026-08-26):** RADIUS core has no realm/tenant concept. Decision: namespace the RADIUS `username` by `tenant_slug` inside the adapter (§4.1.1). No realm mapping required.
4. **RLS policy authoring:** write actual PG policies + `SET app.current_tenant` in a session hook.
5. **Known menu route mismatches** (§5.0) — decide final routes.
6. **Security baseline sign-off (§9):** confirm CSP policy, MFA provider (TOTP/WebAuthn), and secret vault choice before P0 code freeze.

**Risks:**
- RADIUS API contract may differ from §4.2 → isolate behind `RadiusClient` so changes stay in one adapter.
- Multi-tenant RLS misconfig → guarded by tenant-scoped repositories (§3.1, §8).
- PHP/HTML server-rendered + on-the-fly theme → ensure `data-theme` set pre-paint to avoid flash.

---

## Appendix A — Reference SRD Analysis
The provided reference described a *full-stack* platform including its own RADIUS engine. Adjustments made:
1. Protocol engine → external dependency (integration, §4).
2. SLOs re-attributed to external server (§6).
3. RLS retained as central tenancy mechanism (§3.1).
4. IPDR/KYC/GST kept but consumed via API (§5.5).
5. Wallet/LCO/billing unchanged (§5.4, §5.6).
6. Topology split into control plane (this) vs data plane (RADIUS) (§2.1).
7. RADIUS-engine languages dropped from our stack (§7.1).
