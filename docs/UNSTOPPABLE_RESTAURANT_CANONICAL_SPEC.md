# UNSTOPPABLE RESTAURANT

## CANONICAL PROJECT INSTRUCTIONS (DO NOT OVERRIDE)

> This document defines the **non-negotiable architecture, rules, and roadmap** for the Unstoppable Restaurant plugin (Free + Pro).
> Any future work MUST align with this document.
> If instructions conflict, THIS DOCUMENT WINS.

---

## 1. PROJECT IDENTITY (LOCKED)

**Product Name:**
Unstoppable Restaurant

**Product Type:**
Restaurant Ordering Operating System for WooCommerce
(Not just a menu plugin)

**Target Users:**
Restaurants, food trucks, cafes, bars, takeout & delivery shops

**Design Philosophy:**

* Restaurant-first, not Woo-first
* Event-driven, not UI-driven
* Performance > features
* Real kitchens > demos
* No browser hacks
* No blocking checkout
* No site-specific hacks

---

## 2. PLUGIN STRUCTURE (LOCKED)

### Free Plugin

* Slug: `unstoppable-restaurant`
* Purpose: Menu, UX, ordering foundation
* MUST NEVER depend on Pro

### Pro Plugin

* Slug: `unstoppable-restaurant-pro`
* Purpose: Printing, automation, messaging, intelligence
* Extends Free **only via hooks/filters**

**Rule:**
Free plugin must fully function without Pro installed.

---

## 3. CORE PRINCIPLES (NON-NEGOTIABLE)

1. **Never block checkout**
2. **Never auto-print via browser**
3. **Never run heavy logic on every page load**
4. **All side effects are async**
5. **Data → Routing → Rendering → Transport**
6. **Structured payloads, not HTML blobs**
7. **Manual fallback always exists**
8. **Performance regressions are release blockers**

---

## 4. CURRENT STATUS (AS OF v1.1.0 PRO)

### Completed / In Progress

* Conversational Modifiers ✅
* Product Labels ✅
* Menu Rendering (Core) ✅
* Sorting (Core) ✅
* Scheduling (Basic) ✅
* Pro Scaffold ✅
* Auto-Printing Design (Phase 9) ✅
* Phase 9.1 partial implementation (queue, routing, payloads) ✅

### Explicitly Deferred

* SMS
* KDS
* Loyalty
* Analytics
* Capacity throttling
* OSRM delivery zones

---

## 5. AUTO-PRINTING SYSTEM (AUTHORITATIVE)

### Printing is PRO ONLY

Free plugin contains **ZERO printing logic**.

---

## 5.1 Core Printing Entities (LOCKED)

### Printer

* Stored in `wp_options`
* Represents a physical station (Kitchen, Bar, Counter, Expo)
* Connection types:
  * BRIDGE (PrintNode, QZ Tray, custom local bridge)
  * NETWORK (ESC/POS over IP)
* Browser printing = **manual fallback only**

### PrintJob

* Stored as order meta (`_urst_print_jobs`)
* Never blocks order creation
* Fully async
* Retryable
* Logged via order notes

### PrintTemplate

* Stored in `wp_options`
* Station-specific
* Thermal-first
* Emphasis-aware (NO / EXTRA / SIDE / ALLERGY)

---

## 5.2 Auto-Print Triggers (LOCKED)

Supported triggers:

* order_created (not recommended default)
* payment_complete
* status_processing (**RECOMMENDED DEFAULT**)
* status_completed
* status_changed (configurable)
* manual (always allowed, no idempotency)

**Rule:**
Auto-print defaults to `status_processing`, not checkout.

---

## 5.3 Idempotency Rules (LOCKED)

* Deterministic keys (no timestamps)
* Per-trigger dedupe window (default 30s)
* Manual prints bypass idempotency

Examples:

```
{order_id}:processing:{printer_id}
{order_id}:paid:{printer_id}
{order_id}:status:{status_to}:{printer_id}
```

---

## 5.4 Routing Engine (LOCKED)

Routing decides:

* WHICH station(s)
* WHICH printer(s)
* WHICH items
* WHETHER to split tickets

Routing can output **multiple TicketGroups** per order.

### Split Tickets are FIRST-CLASS

* Drinks → Bar
* Food → Kitchen
* Receipts → Counter

---

## 5.5 Payload Architecture (LOCKED)

### OrderPayload

* Full structured representation of order
* Contains:
  * modifiers
  * labels
  * allergy flags
  * fulfillment windows
  * pickup names
  * channel (web / qr / kiosk)

### TicketPayload

* Slice of OrderPayload
* One station only
* Used by:
  * Printing
  * SMS (future)
  * KDS (future)

**Rule:**
Adapters NEVER touch `WC_Order` directly.

---

## 6. PRINTING ADAPTERS (LOCKED)

| Adapter               | Auto-Print | Status      |
| --------------------- | ---------- | ----------- |
| Bridge (PrintNode/QZ) | ✅          | PRIMARY     |
| Network (ESC/POS IP)  | ✅          | Advanced    |
| Browser               | ❌          | Manual only |

**Browser printing must NEVER be used for auto-print.**

---

## 7. QUEUE & EXECUTION (LOCKED)

* Jobs are queued, not executed inline
* Queue processing callable via:
  * `do_action('urst_process_print_queue')`
  * REST endpoint (Pro, authenticated)
* WP-Cron is fallback only

---

## 8. ADMIN UX (LOCKED LANGUAGE)

Admin menu (Pro):

```
Restaurant
└── Pro Settings
    ├── Printers
    ├── Auto-Print
    ├── Ticket Routing
    ├── Ticket Templates
    ├── Print Queue
    └── Test Print
```

Use restaurant language:

* "Send to Kitchen"
* "Reprint Ticket"
* "Bar Printer"
* "Expo"

---

## 9. CURRENT IMPLEMENTATION PHASE

### Phase 9.1 — IN PROGRESS

* Data storage (options + order meta)
* PayloadBuilder
* RoutingEngine
* PrintQueue
* Manual Print (Admin)
* NO real printing yet

### Phase 9.2 — NEXT

* PrintNode adapter
* ESC/POS renderer
* Auto-print on Processing
* Test Print functionality

---

## 10. FORBIDDEN ACTIONS (HARD STOPS)

Claude MUST NOT:

* Add printing to Free plugin
* Add browser auto-printing
* Add synchronous API calls during checkout
* Run queue logic globally
* Introduce site-specific hacks
* Rewrite architecture to "fix" bad sites
* Skip phases

---

## 11. DECISION RULE

If uncertain:

1. Favor restaurant reality
2. Favor async safety
3. Favor performance
4. Favor extensibility
5. Leave TODOs instead of guessing

---

## 12. AUTHORITY STATEMENT

> This document represents the **source of truth** for Unstoppable Restaurant.
> All future work must align to it.
> Deviations must be explicitly justified and approved.
